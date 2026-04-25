/**
 * Menu-tree ↔ Gutenberg block-tree bridge.
 *
 * The C4 backend persists navigations as Gutenberg block trees inside
 * the `{ raw, blocks }` envelope (see `docs/core-data-shim.md` §Navigation).
 * The D4 editor, however, is a NATIVE TREE EDITOR per design brief §3.8 —
 * users drag-sort typed-reference items, not blocks. This module is the
 * translation layer between the two.
 *
 * Read direction (block tree → MenuItem tree):
 *   - `core/navigation-link`     → `{ type: page|post|custom, target_id, label_override?, url? }`
 *   - `core/navigation-submenu`  → same fields + `children`, walked recursively
 *   - Anything else is dropped silently. The native editor has no UI for
 *     block types it doesn't understand, and the alternative (preserving
 *     opaque "unknown" rows the user can't act on) is worse than losing
 *     them.
 *
 * Write direction (MenuItem tree → block tree):
 *   - Items with no children round-trip as `core/navigation-link`.
 *   - Items with children round-trip as `core/navigation-submenu`,
 *     children rendered recursively as `innerBlocks`.
 *   - Type → block-attribute kind mapping mirrors WP's: `page`/`post` →
 *     `kind: 'post-type'` + `type` + `id`; `custom` → bare `url`/`label`.
 *
 * The `raw` half of the envelope is left empty; the backend regenerates
 * it from `blocks` on save (see `VisualEditorNavigation::setContentEnvelope`).
 */

export type MenuItemType = 'page' | 'post' | 'custom' | 'taxonomy';

export interface MenuItem {
    /** Locally stable id used by react keys + dnd-kit; not persisted. */
    localId: string;
    type: MenuItemType;
    /** When the item points at an entity, this is the typed reference. */
    targetId: number | string | null;
    /** Optional human override; falsy → render the auto-derived label. */
    labelOverride: string | null;
    /**
     * Auto-derived label from the target entity at read-time. Useful for
     * rendering the tree without a separate fetch. The save path doesn't
     * persist this — the backend re-resolves on next read.
     */
    autoLabel: string;
    /** Custom-URL items hold their URL here. */
    url: string | null;
    /** Open in new tab. */
    opensInNewTab: boolean;
    /** Free-form CSS classes. */
    className: string | null;
    /** Free-form rel attribute. */
    rel: string | null;
    children: MenuItem[];
}

interface UnknownBlock {
    name?: unknown;
    attributes?: unknown;
    innerBlocks?: unknown;
    [key: string]: unknown;
}

const NAV_LINK = 'core/navigation-link';
const NAV_SUBMENU = 'core/navigation-submenu';

/**
 * Generates a stable-enough local id for tree-editor react keys. The id
 * never round-trips to the backend — it lives only on the in-memory
 * MenuItem tree to back drag-sort and edits.
 */
let counter = 0;
export function nextLocalId(): string {
    counter += 1;

    return `menu-item-${counter}-${Date.now().toString(36)}`;
}

function readString(value: unknown, fallback = ''): string {
    return typeof value === 'string' ? value : fallback;
}

function readMaybeString(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    return value === '' ? null : value;
}

function readBool(value: unknown): boolean {
    return value === true;
}

function readMaybeIdentifier(value: unknown): number | string | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value !== '') {
        return value;
    }

    return null;
}

/**
 * Maps a `core/navigation-link` / `core/navigation-submenu` block's
 * `{ kind, type }` attributes onto our flat `MenuItemType`.
 */
function blockAttributesToType(
    attributes: Record<string, unknown>
): MenuItemType {
    const kind = readString(attributes.kind);
    const type = readString(attributes.type);

    if (kind === 'post-type') {
        if (type === 'page') {
            return 'page';
        }
        if (type === 'post') {
            return 'post';
        }
    }

    if (kind === 'taxonomy') {
        return 'taxonomy';
    }

    // Bare URL items have neither `kind` nor `type` set in WP's wire
    // shape — we treat them as the catch-all custom URL.
    return 'custom';
}

function menuItemTypeToAttributes(
    type: MenuItemType
): { kind: string; type?: string } {
    if (type === 'page' || type === 'post') {
        return { kind: 'post-type', type };
    }

    if (type === 'taxonomy') {
        return { kind: 'taxonomy' };
    }

    return { kind: 'custom' };
}

/**
 * Walks a block tree and returns the corresponding MenuItem tree.
 * Drops blocks whose `name` we don't know how to translate.
 */
export function blocksToMenuTree(blocks: readonly unknown[]): MenuItem[] {
    const items: MenuItem[] = [];

    for (const raw of blocks) {
        if (raw === null || typeof raw !== 'object') {
            continue;
        }

        const block = raw as UnknownBlock;
        const name = readString(block.name);

        if (name !== NAV_LINK && name !== NAV_SUBMENU) {
            continue;
        }

        const attributes =
            block.attributes !== null && typeof block.attributes === 'object'
                ? (block.attributes as Record<string, unknown>)
                : {};

        const innerBlocks = Array.isArray(block.innerBlocks)
            ? (block.innerBlocks as readonly unknown[])
            : [];

        const item: MenuItem = {
            localId: nextLocalId(),
            type: blockAttributesToType(attributes),
            targetId: readMaybeIdentifier(attributes.id),
            // The wire format has a single `label` attribute — there is
            // no signal whether it originated as an auto-derived label
            // or a user override. Treat it as the auto label on read;
            // the user re-asserts an override the next time they edit
            // the field. `effectiveLabel` returns the same string
            // either way, so the visible behavior is unchanged.
            labelOverride: null,
            autoLabel: readString(attributes.label),
            url: readMaybeString(attributes.url),
            opensInNewTab: readBool(attributes.opensInNewTab),
            className: readMaybeString(attributes.className),
            rel: readMaybeString(attributes.rel),
            children:
                name === NAV_SUBMENU ? blocksToMenuTree(innerBlocks) : [],
        };

        items.push(item);
    }

    return items;
}

/**
 * Walks a MenuItem tree and emits the matching block tree. Submenus
 * win against bare links whenever an item has children — this is what
 * Gutenberg's `core/navigation` editor expects, and matches the
 * backend's parse pipeline.
 */
export function menuTreeToBlocks(items: readonly MenuItem[]): unknown[] {
    return items.map((item) => menuItemToBlock(item));
}

function menuItemToBlock(item: MenuItem): Record<string, unknown> {
    const attributes: Record<string, unknown> = {
        ...menuItemTypeToAttributes(item.type),
        label:
            item.labelOverride !== null && item.labelOverride !== ''
                ? item.labelOverride
                : item.autoLabel,
    };

    if (item.targetId !== null) {
        attributes.id = item.targetId;
    }

    if (item.url !== null) {
        attributes.url = item.url;
    }

    if (item.opensInNewTab) {
        attributes.opensInNewTab = true;
    }

    if (item.className !== null) {
        attributes.className = item.className;
    }

    if (item.rel !== null) {
        attributes.rel = item.rel;
    }

    if (item.children.length === 0) {
        return {
            name: NAV_LINK,
            attributes,
            innerBlocks: [],
        };
    }

    return {
        name: NAV_SUBMENU,
        attributes,
        innerBlocks: menuTreeToBlocks(item.children),
    };
}

export function makeMenuItem(overrides: Partial<MenuItem> = {}): MenuItem {
    return {
        localId: overrides.localId ?? nextLocalId(),
        type: overrides.type ?? 'custom',
        targetId: overrides.targetId ?? null,
        labelOverride: overrides.labelOverride ?? null,
        autoLabel: overrides.autoLabel ?? '',
        url: overrides.url ?? null,
        opensInNewTab: overrides.opensInNewTab ?? false,
        className: overrides.className ?? null,
        rel: overrides.rel ?? null,
        children: overrides.children ?? [],
    };
}

/** Returns the visible label — override wins, falls back to auto. */
export function effectiveLabel(item: MenuItem): string {
    if (item.labelOverride !== null && item.labelOverride !== '') {
        return item.labelOverride;
    }

    if (item.autoLabel !== '') {
        return item.autoLabel;
    }

    if (item.url !== null && item.url !== '') {
        return item.url;
    }

    return '';
}

/**
 * Recursively replaces an item by `localId`. Returns a new tree (no
 * mutation) so React state updates are safe.
 */
export function replaceMenuItem(
    tree: readonly MenuItem[],
    localId: string,
    update: (item: MenuItem) => MenuItem
): MenuItem[] {
    return tree.map((item) => {
        if (item.localId === localId) {
            return update(item);
        }

        if (item.children.length === 0) {
            return item;
        }

        return {
            ...item,
            children: replaceMenuItem(item.children, localId, update),
        };
    });
}

export function removeMenuItem(
    tree: readonly MenuItem[],
    localId: string
): MenuItem[] {
    const filtered: MenuItem[] = [];

    for (const item of tree) {
        if (item.localId === localId) {
            continue;
        }

        filtered.push({
            ...item,
            children: removeMenuItem(item.children, localId),
        });
    }

    return filtered;
}

export function appendChild(
    tree: readonly MenuItem[],
    parentLocalId: string,
    child: MenuItem
): MenuItem[] {
    return tree.map((item) => {
        if (item.localId === parentLocalId) {
            return {
                ...item,
                children: [...item.children, child],
            };
        }

        if (item.children.length === 0) {
            return item;
        }

        return {
            ...item,
            children: appendChild(item.children, parentLocalId, child),
        };
    });
}

/** Locate an item in the tree by `localId`. Returns `null` on miss. */
export function findMenuItem(
    tree: readonly MenuItem[],
    localId: string
): MenuItem | null {
    for (const item of tree) {
        if (item.localId === localId) {
            return item;
        }

        const child = findMenuItem(item.children, localId);

        if (child !== null) {
            return child;
        }
    }

    return null;
}

/**
 * Reorders `tree[fromIndex]` to `toIndex` at the top level.
 * Out-of-range indices return the tree unchanged.
 */
export function reorderTopLevel(
    tree: readonly MenuItem[],
    fromIndex: number,
    toIndex: number
): MenuItem[] {
    if (
        fromIndex < 0 ||
        fromIndex >= tree.length ||
        toIndex < 0 ||
        toIndex >= tree.length ||
        fromIndex === toIndex
    ) {
        return tree.slice();
    }

    const next = tree.slice();
    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);

    return next;
}

// ---------------------------------------------------------------------------
// Flat-list bridge — used by the dnd-kit "tree" pattern in
// `navigation-canvas.tsx`. Drag-and-drop wants a single flat sortable
// list, so we render from a flattened view and rebuild the nested
// MenuItem tree on commit.
// ---------------------------------------------------------------------------

/**
 * One row in the flat view of a MenuItem tree. `depth` is 0 at the
 * root and increases by 1 per generation. `parentLocalId` is the
 * direct parent's `localId`, or `null` at the root.
 */
export interface FlatMenuItem {
    item: MenuItem;
    depth: number;
    parentLocalId: string | null;
    /** True when the item carries its own children. */
    hasChildren: boolean;
}

/**
 * Walk the tree depth-first and emit one `FlatMenuItem` per node so
 * the canvas can hand a single sortable list to dnd-kit.
 */
export function flattenTree(
    tree: readonly MenuItem[],
    parentLocalId: string | null = null,
    depth = 0
): FlatMenuItem[] {
    const out: FlatMenuItem[] = [];

    for (const item of tree) {
        out.push({
            item,
            depth,
            parentLocalId,
            hasChildren: item.children.length > 0,
        });

        if (item.children.length > 0) {
            out.push(
                ...flattenTree(item.children, item.localId, depth + 1)
            );
        }
    }

    return out;
}

/**
 * Inverse of `flattenTree`. Rebuilds a nested `MenuItem[]` from a
 * flat list, using each row's `parentLocalId` as the authoritative
 * link (depth is rendering metadata only — it can't disambiguate
 * sibling subtrees that happen to sit at the same level).
 *
 * The implementation is order-independent: the dnd-kit drag-end path
 * arrayMoves a single row without resorting the flat list, so a
 * child can appear before its parent. Two passes — first materialize
 * every item, then attach in flat-list order — keep that behavior
 * stable. Children of the same parent stay in their flat-list order
 * so user-perceived sibling order survives the round trip.
 */
export function buildTreeFromFlat(
    flat: readonly FlatMenuItem[]
): MenuItem[] {
    const itemsById = new Map<string, MenuItem>();

    for (const flatItem of flat) {
        // Reset `children` — we re-derive parent/child wiring from
        // the flat list itself, not whatever was nested on input.
        itemsById.set(flatItem.item.localId, {
            ...flatItem.item,
            children: [],
        });
    }

    const root: MenuItem[] = [];

    for (const flatItem of flat) {
        const rebuilt = itemsById.get(flatItem.item.localId);

        if (rebuilt === undefined) {
            continue;
        }

        if (flatItem.parentLocalId === null) {
            root.push(rebuilt);
            continue;
        }

        const parent = itemsById.get(flatItem.parentLocalId);

        if (parent === undefined) {
            // Orphan with a non-null parent — re-root so the item
            // doesn't disappear. This shouldn't happen with a
            // well-formed flat list but guards the round-trip.
            root.push(rebuilt);
            continue;
        }

        parent.children.push(rebuilt);
    }

    return root;
}

/**
 * Returns the localIds of the descendant items beneath the given
 * ancestor. Used by the canvas to "lift" a subtree out of the flat
 * list while it's being dragged so the dragged item doesn't pretend
 * to drop INTO its own children.
 *
 * Order-independent: builds a parent → children map and runs a BFS
 * from the ancestor, so the result is stable regardless of whether
 * children appear before or after their parent in the flat list
 * (the drag-end commit path can produce an unsorted layout).
 */
export function getDescendantIds(
    flat: readonly FlatMenuItem[],
    ancestorLocalId: string
): Set<string> {
    const childrenByParent = new Map<string, string[]>();

    for (const flatItem of flat) {
        if (flatItem.parentLocalId === null) {
            continue;
        }

        const bucket = childrenByParent.get(flatItem.parentLocalId);

        if (bucket === undefined) {
            childrenByParent.set(flatItem.parentLocalId, [
                flatItem.item.localId,
            ]);
        } else {
            bucket.push(flatItem.item.localId);
        }
    }

    const descendants = new Set<string>();
    const queue: string[] = [ancestorLocalId];

    while (queue.length > 0) {
        const current = queue.shift() as string;
        const children = childrenByParent.get(current);

        if (children === undefined) {
            continue;
        }

        for (const childId of children) {
            if (descendants.has(childId)) {
                continue;
            }

            descendants.add(childId);
            queue.push(childId);
        }
    }

    return descendants;
}

export interface ProjectionResult {
    depth: number;
    maxDepth: number;
    minDepth: number;
    parentLocalId: string | null;
}

/**
 * Computes the projected depth + parent for the dragged item given:
 *
 *   - the current ordered flat list (with the dragged item still at
 *     its original index),
 *   - the active and over ids,
 *   - the cumulative drag X-offset (positive = right, deepens),
 *   - the indent width in pixels.
 *
 * Returns the depth the canvas should render the dragged row at while
 * a drag is in flight (visual feedback) and that the commit step
 * should snap to on drop.
 *
 * Mirrors the standard dnd-kit "tree" recipe — see
 * https://github.com/clauderic/dnd-kit/blob/master/stories/3%20-%20Examples/Tree/utilities.ts.
 */
export function projectDrop(
    flat: readonly FlatMenuItem[],
    activeId: string,
    overId: string,
    dragOffsetX: number,
    indentWidth: number
): ProjectionResult | null {
    const overIndex = flat.findIndex(
        (entry) => entry.item.localId === overId
    );
    const activeIndex = flat.findIndex(
        (entry) => entry.item.localId === activeId
    );

    if (overIndex === -1 || activeIndex === -1) {
        return null;
    }

    const activeItem = flat[activeIndex];

    // `arrayMove` semantics — pretend the dragged item has already
    // moved to the over-index so the previous / next neighbors reflect
    // the post-drop layout.
    const reordered = flat.slice();
    const [moved] = reordered.splice(activeIndex, 1);
    reordered.splice(overIndex, 0, moved);

    const previousItem = reordered[overIndex - 1];
    const nextItem = reordered[overIndex + 1];
    const dragDepth = Math.round(dragOffsetX / indentWidth);
    const projectedDepth = activeItem.depth + dragDepth;

    const maxDepth =
        previousItem === undefined ? 0 : previousItem.depth + 1;
    const minDepth = nextItem === undefined ? 0 : nextItem.depth;

    let depth = projectedDepth;

    if (depth >= maxDepth) {
        depth = maxDepth;
    } else if (depth < minDepth) {
        depth = minDepth;
    }

    let parentLocalId: string | null = null;

    if (depth > 0 && previousItem !== undefined) {
        if (depth === previousItem.depth) {
            parentLocalId = previousItem.parentLocalId;
        } else if (depth > previousItem.depth) {
            parentLocalId = previousItem.item.localId;
        } else {
            // Walk back through the reordered list to find an ancestor
            // that sits at the projected depth and inherit ITS parent.
            const ancestor = reordered
                .slice(0, overIndex)
                .reverse()
                .find((entry) => entry.depth === depth);

            parentLocalId = ancestor?.parentLocalId ?? null;
        }
    }

    return { depth, maxDepth, minDepth, parentLocalId };
}

/**
 * Commit the active row's projected depth + parent into the flat
 * list, then array-move it from its old index to the over-row's
 * index. Mirrors the dnd-kit "tree" recipe: only the active row
 * moves, its descendants stay in place and re-attach via
 * `parentLocalId` when `buildTreeFromFlat` runs.
 *
 * Returns the new flat list, or the original list when the indices
 * couldn't be resolved (a defensive no-op).
 */
export function commitProjection(
    flat: readonly FlatMenuItem[],
    activeId: string,
    overId: string,
    projection: ProjectionResult
): FlatMenuItem[] {
    const activeIndex = flat.findIndex(
        (entry) => entry.item.localId === activeId
    );
    const overIndex = flat.findIndex(
        (entry) => entry.item.localId === overId
    );

    if (activeIndex === -1 || overIndex === -1) {
        return flat.slice();
    }

    const updated = flat.slice();
    updated[activeIndex] = {
        ...updated[activeIndex],
        depth: projection.depth,
        parentLocalId: projection.parentLocalId,
    };

    const [moved] = updated.splice(activeIndex, 1);
    updated.splice(overIndex, 0, moved);

    return updated;
}
