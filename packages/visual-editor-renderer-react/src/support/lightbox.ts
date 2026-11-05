/**
 * Image block lightbox (#1046).
 *
 * Mirrors the Blade renderer's `ImageLightbox` support class. `bindLightbox()`
 * runs in the browser only and imports the overlay on first use, so pages with
 * no lightbox never download it.
 */

import { attrRecord } from './attributes';
import { safeUrl } from './urlSanitizer';
import type { Block } from '../types';

/** Gallery navigation button types that need telling to the overlay. */
const NAVIGATION_TYPES = ['text', 'both'];

/**
 * The lightbox's user-facing text. Blade translates these through `__()`
 * (`ImageLightbox::labels()`); here a host passes its translations to
 * {@link setLightboxLabels}. `:alt` in `enlargeWithAlt` is the image's alt
 * text, and `:current` / `:total` in `position` are filled in by the
 * overlay.
 */
export interface LightboxLabels {
    dialog: string;
    close: string;
    previous: string;
    next: string;
    position: string;
    enlarge: string;
    enlargeWithAlt: string;
}

const DEFAULT_LIGHTBOX_LABELS: Readonly<LightboxLabels> = {
    dialog: 'Enlarged image',
    close: 'Close',
    previous: 'Previous image',
    next: 'Next image',
    position: 'Image :current of :total',
    enlarge: 'Enlarge image',
    enlargeWithAlt: 'Enlarge image: :alt',
};

let labels: LightboxLabels = { ...DEFAULT_LIGHTBOX_LABELS };

/**
 * Replace some or all of the lightbox's text, for example with the host's
 * translations. Call it before rendering, as with `setBreakpoints()`.
 * Calling it with no argument restores the English defaults.
 */
export function setLightboxLabels(next?: Partial<LightboxLabels>): void {
    labels = next === undefined ? { ...DEFAULT_LIGHTBOX_LABELS } : { ...labels, ...next };
}

/** The lightbox's text in effect. */
export function getLightboxLabels(): Readonly<LightboxLabels> {
    return labels;
}

/** The overlay's labels, for the figure's `data-ap-lightbox-labels`. */
export function lightboxLabelsJson(): string {
    const { dialog, close, previous, next, position } = labels;

    return JSON.stringify({ dialog, close, previous, next, position });
}

/** The trigger's icon path. Decorative: the button has its own label. */
export const LIGHTBOX_TRIGGER_ICON_PATH =
    'M2 0a2 2 0 0 0-2 2v2h1.5V2a.5.5 0 0 1 .5-.5h2V0H2Zm2 10.5H2a.5.5 0 0 1-.5-.5V8H0v2a2 2 0 0 0 2 2h2v-1.5ZM8 12v-1.5h2a.5.5 0 0 0 .5-.5V8H12v2a2 2 0 0 1-2 2H8Zm2-12a2 2 0 0 1 2 2v2h-1.5V2a.5.5 0 0 0-.5-.5H8V0h2Z';

/**
 * Whether an image opens in the lightbox. A block's own `lightbox.enabled`
 * wins over the theme default `stampLightboxDefault()` left on it, and a link
 * wins over both.
 */
export function imageHasLightbox(attributes: Record<string, unknown>): boolean {
    if (safeUrl(attributes.url) === '' || safeUrl(attributes.href) !== '') {
        return false;
    }

    const own = attrRecord(attributes.lightbox).enabled;

    return typeof own === 'boolean' ? own : attributes._veLightbox === true;
}

/** The trigger's accessible name. */
export function lightboxTriggerLabel(alt: string): string {
    return alt.trim() === '' ? labels.enlarge : labels.enlargeWithAlt.split(':alt').join(alt);
}

/** The Gallery's `data-ap-lightbox-navigation` value, if it needs one. */
export function lightboxNavigationType(type: unknown): string | undefined {
    return typeof type === 'string' && NAVIGATION_TYPES.includes(type) ? type : undefined;
}

/**
 * Apply the theme's default to every image in a tree. The theme setting lives
 * on the server, so hosts pass it to `<BlockTree>`, as with `duotonePresets`.
 *
 * `_veLightbox` belongs to the renderer: one already on the input tree, from
 * a pasted or stored block, is replaced, so it can't turn the lightbox on
 * where Blade wouldn't. A tree with nothing to change comes back as is.
 */
export function stampLightboxDefault(tree: Block[], enabled: boolean): Block[] {
    let changed = false;

    const next = tree.map((block) => {
        if (block === null || typeof block !== 'object') {
            return block;
        }

        const innerBlocks = Array.isArray(block.innerBlocks)
            ? stampLightboxDefault(block.innerBlocks, enabled)
            : block.innerBlocks;
        const isImage = block.name === 'core/image' || block.name === 'artisanpack/image';
        const attributes = attrRecord(block.attributes);
        const stamp = isImage && (enabled ? attributes._veLightbox !== true : '_veLightbox' in attributes);

        if (!stamp && innerBlocks === block.innerBlocks) {
            return block;
        }

        changed = true;

        if (!stamp) {
            return { ...block, innerBlocks };
        }

        const stamped: Record<string, unknown> = { ...attributes };

        if (enabled) {
            stamped._veLightbox = true;
        } else {
            delete stamped._veLightbox;
        }

        return { ...block, attributes: stamped, innerBlocks };
    });

    return changed ? next : tree;
}

/**
 * Set up a lightbox figure. The image is already on the page, so an overlay
 * chunk that fails to load, as it can on a flaky network, just leaves it an
 * ordinary image.
 */
export async function bindLightbox(element: HTMLElement): Promise<void> {
    try {
        const overlay = await import('./lightboxOverlay.generated.js');

        overlay.bindLightbox(element);
    } catch {
        // See above.
    }
}
