/**
 * Masonry JS fallback (#593).
 *
 * Renderer-agnostic shortest-column-first packing for browsers that do
 * not ship native CSS Grid Level 3 masonry. Used by:
 *
 *   - `artisanpack/post-template` editor preview (always — see #593)
 *   - `artisanpack/grid` editor preview (always)
 *   - The Blade / React / Vue renderers on the public frontend, where
 *     each ships a tiny bootstrap that walks the page for wrappers
 *     carrying `.is-layout-masonry` / `.ap-grid-layout-masonry` and
 *     calls {@link initMasonry} on them.
 *
 * The fallback:
 *
 *   - Reads the configured column count from the container (either passed
 *     via {@link MasonryOptions} or derived from a `data-ap-cols` /
 *     `--ap-masonry-cols` custom property when invoked from the renderer
 *     bootstrap).
 *   - Honours each direct child's `data-ap-col-span` (or `gridColumnSpan`-
 *     equivalent class on the item). Wider items skip columns under
 *     them in the packing pass.
 *   - Listens for resize, child mutations, and image-load events; relayouts
 *     idempotently on each.
 *   - Tears down cleanly via {@link MasonryController#destroy}, restoring
 *     the original wrapper / child styles.
 */

const ITEM_CLASS = 'ap-masonry-item';
const WRAPPER_CLASS = 'ap-masonry-js-fallback';
const SUPPORTS_CACHE_KEY = '__apMasonrySupports';

/**
 * Tailwind-style breakpoint min-widths in ascending order. Mirrors
 * `resources/js/visual-editor/responsive/registry.ts`. Encoded inline
 * here because the JS fallback is renderer-agnostic and must stay
 * decoupled from the editor's responsive registry.
 */
const BREAKPOINT_MIN_WIDTHS: ReadonlyArray<readonly [string, number]> = [
    ['sm', 640],
    ['md', 768],
    ['lg', 1024],
    ['xl', 1280],
    ['2xl', 1536],
];

interface SupportsCacheHost {
    [SUPPORTS_CACHE_KEY]?: boolean;
}

export interface MasonryOptions {
    /** Number of columns. Falls back to data-ap-cols / 3. */
    columns?: number;
    /** Skip the @supports gate and always run the fallback. Editor sets this. */
    forceFallback?: boolean;
    /** Override the gap (px). Defaults to the computed `gap` on the wrapper. */
    gap?: number;
}

export interface MasonryController {
    /** Force an immediate relayout. */
    relayout(): void;
    /** Remove all observers + restore inline styles. */
    destroy(): void;
}

/**
 * Feature-detect native CSS Grid masonry support. Cached on the global
 * so subsequent calls reuse the same answer — the spec rule is sticky
 * for the page lifetime.
 */
export function supportsNativeMasonry(): boolean {
    if (typeof window === 'undefined' || typeof CSS === 'undefined') {
        return false;
    }
    const host = window as SupportsCacheHost;
    const cached = host[SUPPORTS_CACHE_KEY];
    if (typeof cached === 'boolean') {
        return cached;
    }
    let result = false;
    try {
        result = CSS.supports('grid-template-rows', 'masonry');
    } catch {
        result = false;
    }
    host[SUPPORTS_CACHE_KEY] = result;
    return result;
}

interface ItemPlacement {
    el: HTMLElement;
    span: number;
    /** Pre-stored inline style fragments so destroy() can restore them. */
    prevStyle: {
        position: string;
        top: string;
        left: string;
        width: string;
        boxSizing: string;
    };
}

function clampColumns(value: number | undefined, fallback: number): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return fallback;
    }
    const truncated = Math.trunc(value);
    if (truncated < 1) {
        return 1;
    }
    if (truncated > 12) {
        return 12;
    }
    return truncated;
}

function readColumnsFromAttr(container: HTMLElement, fallback: number): number {
    const attr = container.getAttribute('data-ap-cols');
    if (attr === null || attr === '') {
        return fallback;
    }
    const parsed = Number(attr);
    return clampColumns(parsed, fallback);
}

/**
 * Walk the breakpoint table in ascending min-width order and pick the
 * column count from the widest breakpoint whose min-width the current
 * viewport meets. Falls back to the base `data-ap-cols` (or the given
 * fallback) when no breakpoint matches.
 */
function readActiveColumnsFromAttrs(container: HTMLElement, fallback: number): number {
    const base = readColumnsFromAttr(container, fallback);
    if (typeof window === 'undefined') {
        return base;
    }
    const viewportWidth = window.innerWidth ?? 0;
    let active = base;
    for (const [bp, minWidth] of BREAKPOINT_MIN_WIDTHS) {
        if (viewportWidth < minWidth) {
            break;
        }
        const attr = container.getAttribute(`data-ap-cols-${bp}`);
        if (attr === null || attr === '') {
            continue;
        }
        const parsed = Number(attr);
        if (!Number.isFinite(parsed)) {
            continue;
        }
        active = clampColumns(parsed, active);
    }
    return active;
}

function readItemSpan(el: HTMLElement, maxColumns: number): number {
    // Prefer the explicit data attribute the renderers stamp.
    const dataAttr = el.getAttribute('data-ap-col-span');
    if (dataAttr !== null) {
        const parsed = Number(dataAttr);
        if (Number.isFinite(parsed)) {
            return Math.min(maxColumns, Math.max(1, Math.trunc(parsed)));
        }
    }

    // Fall back to the grid-item span class so author-set
    // `gridColumnSpan` keeps applying inside masonry (#593 acceptance).
    const classNames = el.classList;
    for (let span = maxColumns; span >= 2; span -= 1) {
        if (classNames.contains(`ap-grid-item-span-${span}-base-columns`)) {
            return span;
        }
    }

    return 1;
}

function readGapPx(container: HTMLElement, override?: number): number {
    if (typeof override === 'number' && Number.isFinite(override)) {
        return Math.max(0, override);
    }
    const computed = typeof window !== 'undefined' ? window.getComputedStyle(container) : null;
    if (computed === null) {
        return 0;
    }
    const raw = computed.rowGap || computed.gap || '0';
    const parsed = parseFloat(raw);
    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

function collectItems(container: HTMLElement): HTMLElement[] {
    const items: HTMLElement[] = [];
    for (const child of Array.from(container.children)) {
        if (child instanceof HTMLElement) {
            items.push(child);
        }
    }
    return items;
}

function snapshotStyle(el: HTMLElement): ItemPlacement['prevStyle'] {
    return {
        position: el.style.position,
        top: el.style.top,
        left: el.style.left,
        width: el.style.width,
        boxSizing: el.style.boxSizing,
    };
}

function restoreStyle(el: HTMLElement, prev: ItemPlacement['prevStyle']): void {
    el.style.position = prev.position;
    el.style.top = prev.top;
    el.style.left = prev.left;
    el.style.width = prev.width;
    el.style.boxSizing = prev.boxSizing;
    el.classList.remove(ITEM_CLASS);
}

/**
 * Initialise the masonry fallback on a container. Returns a controller
 * the caller can use to force a relayout or tear the fallback down.
 *
 * If the browser supports native CSS Grid masonry AND `forceFallback`
 * is not set, this is a no-op and the returned controller's `relayout`
 * and `destroy` methods do nothing — letting the consumer treat the
 * call site as unconditional.
 */
export function initMasonry(container: HTMLElement, options: MasonryOptions = {}): MasonryController {
    if (!options.forceFallback && supportsNativeMasonry()) {
        return { relayout: () => undefined, destroy: () => undefined };
    }

    let destroyed = false;
    let placements: ItemPlacement[] = [];
    // Forward-declared so `relayout()` can call `unobserve()` on items
    // that disappear between layout passes. Assigned just before the
    // first `relayout()` invocation below.
    let resizeObserver: ResizeObserver | null = null;

    const initialWrapperStyle = {
        position: container.style.position,
        height: container.style.height,
    };
    const initialClassesAdded: string[] = [];
    if (!container.classList.contains(WRAPPER_CLASS)) {
        container.classList.add(WRAPPER_CLASS);
        initialClassesAdded.push(WRAPPER_CLASS);
    }

    const trackPlacement = (el: HTMLElement, span: number): ItemPlacement => {
        const placement: ItemPlacement = {
            el,
            span,
            prevStyle: snapshotStyle(el),
        };
        if (!el.classList.contains(ITEM_CLASS)) {
            el.classList.add(ITEM_CLASS);
        }
        return placement;
    };

    const relayout = (): void => {
        if (destroyed) {
            return;
        }

        const items = collectItems(container);
        const columns = clampColumns(
            options.columns ?? readActiveColumnsFromAttrs(container, 3),
            3,
        );
        const gap = readGapPx(container, options.gap);

        // Refresh placement tracking so newly added items are styled and
        // removed items have their styles restored. Unobserve removed
        // items from the ResizeObserver so the observer set doesn't
        // accumulate detached targets across mutations.
        const liveSet = new Set(items);
        for (const placement of placements) {
            if (!liveSet.has(placement.el)) {
                restoreStyle(placement.el, placement.prevStyle);
                resizeObserver?.unobserve(placement.el);
            }
        }
        const placementByEl = new Map(placements.map((p) => [p.el, p] as const));
        const next: ItemPlacement[] = [];
        for (const item of items) {
            const span = readItemSpan(item, columns);
            const existing = placementByEl.get(item);
            if (existing !== undefined) {
                existing.span = span;
                next.push(existing);
            } else {
                next.push(trackPlacement(item, span));
            }
        }
        placements = next;

        const containerWidth = container.clientWidth;
        if (containerWidth <= 0 || items.length === 0) {
            container.style.height = '0px';
            return;
        }

        const totalGap = gap * (columns - 1);
        const columnWidth = (containerWidth - totalGap) / columns;
        const columnHeights = new Array<number>(columns).fill(0);

        for (const placement of placements) {
            const span = Math.min(placement.span, columns);
            const slot = findSlot(columnHeights, span);
            const left = slot * (columnWidth + gap);
            const top = sliceMaxHeight(columnHeights, slot, span);
            const width = columnWidth * span + gap * (span - 1);

            placement.el.style.position = 'absolute';
            placement.el.style.boxSizing = 'border-box';
            placement.el.style.left = `${left}px`;
            placement.el.style.top = `${top}px`;
            placement.el.style.width = `${width}px`;

            const itemHeight = placement.el.offsetHeight;
            const newColumnHeight = top + itemHeight + gap;
            for (let i = slot; i < slot + span; i += 1) {
                columnHeights[i] = newColumnHeight;
            }
        }

        const maxHeight = Math.max(0, ...columnHeights) - gap;
        container.style.height = `${Math.max(0, maxHeight)}px`;
    };

    // Initial layout. Defer to a microtask so consumers that init() right
    // after mounting catch the children once they've actually painted.
    relayout();

    // Observe the container AND each item for size changes — when an
    // item's content first paints (editor canvas case) its size jumps
    // from 0 to N, but the container's size doesn't, so the
    // container-only observation misses it.
    resizeObserver = typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(() => relayout())
        : null;
    resizeObserver?.observe(container);

    const observedItems = new WeakSet<HTMLElement>();
    const observeNewItems = (): void => {
        if (!resizeObserver) {
            return;
        }
        for (const placement of placements) {
            if (!observedItems.has(placement.el)) {
                resizeObserver.observe(placement.el);
                observedItems.add(placement.el);
            }
        }
    };
    observeNewItems();

    const mutationObserver = typeof MutationObserver !== 'undefined'
        ? new MutationObserver(() => {
              relayout();
              observeNewItems();
          })
        : null;
    mutationObserver?.observe(container, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'data-ap-col-span', 'data-ap-cols'],
    });

    const onImgLoad = (event: Event): void => {
        const target = event.target;
        if (target instanceof HTMLImageElement && container.contains(target)) {
            relayout();
        }
    };
    container.addEventListener('load', onImgLoad, true);

    const onWindowResize = (): void => relayout();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', onWindowResize);
    }

    // Tame the empty-then-painted race: Gutenberg's editor canvas
    // mounts post-template items as empty `<li>` wrappers and then
    // streams the inner-block tree into them across several frames.
    // The MutationObserver above eventually catches it, but a couple
    // of rAF-paced relayouts let the canvas paint correctly within
    // the first ~50ms instead of waiting on a mutation we don't
    // control.
    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
        let kicks = 0;
        const kick = (): void => {
            if (destroyed || kicks > 3) {
                return;
            }
            kicks += 1;
            relayout();
            window.requestAnimationFrame(kick);
        };
        window.requestAnimationFrame(kick);
    }

    return {
        relayout,
        destroy(): void {
            if (destroyed) {
                return;
            }
            destroyed = true;
            resizeObserver?.disconnect();
            mutationObserver?.disconnect();
            container.removeEventListener('load', onImgLoad, true);
            if (typeof window !== 'undefined') {
                window.removeEventListener('resize', onWindowResize);
            }
            for (const placement of placements) {
                restoreStyle(placement.el, placement.prevStyle);
            }
            placements = [];
            container.style.position = initialWrapperStyle.position;
            container.style.height = initialWrapperStyle.height;
            for (const cls of initialClassesAdded) {
                container.classList.remove(cls);
            }
        },
    };
}

/**
 * Walk the column heights array and return the index where a span-wide
 * window starts at the shortest max height. For a 1-wide span this is
 * just the shortest column; for an N-wide span we evaluate every valid
 * starting slot and pick the one whose window-max is the smallest.
 */
function findSlot(columnHeights: ReadonlyArray<number>, span: number): number {
    if (span >= columnHeights.length) {
        return 0;
    }
    let bestSlot = 0;
    let bestMax = Number.POSITIVE_INFINITY;
    const lastStart = columnHeights.length - span;
    for (let start = 0; start <= lastStart; start += 1) {
        let windowMax = -Infinity;
        for (let i = start; i < start + span; i += 1) {
            if (columnHeights[i] > windowMax) {
                windowMax = columnHeights[i];
            }
        }
        if (windowMax < bestMax) {
            bestMax = windowMax;
            bestSlot = start;
        }
    }
    return bestSlot;
}

function sliceMaxHeight(columnHeights: ReadonlyArray<number>, slot: number, span: number): number {
    let max = 0;
    for (let i = slot; i < slot + span; i += 1) {
        if (columnHeights[i] > max) {
            max = columnHeights[i];
        }
    }
    return max;
}
