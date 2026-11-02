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

/** The overlay's labels, for the figure's `data-ap-lightbox-labels`. */
export const LIGHTBOX_LABELS = JSON.stringify({
    dialog: 'Enlarged image',
    close: 'Close',
    previous: 'Previous image',
    next: 'Next image',
});

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
    return alt.trim() === '' ? 'Enlarge image' : `Enlarge image: ${alt}`;
}

/** The Gallery's `data-ap-lightbox-navigation` value, if it needs one. */
export function lightboxNavigationType(type: unknown): string | undefined {
    return typeof type === 'string' && NAVIGATION_TYPES.includes(type) ? type : undefined;
}

/**
 * Apply the theme's default to every image in a tree. The theme setting lives
 * on the server, so hosts pass it to `<BlockTree>`, as with `duotonePresets`.
 */
export function stampLightboxDefault(tree: Block[], enabled: boolean): Block[] {
    if (!enabled) {
        return tree;
    }

    return tree.map((block) => {
        if (block === null || typeof block !== 'object') {
            return block;
        }

        const innerBlocks = Array.isArray(block.innerBlocks)
            ? stampLightboxDefault(block.innerBlocks, enabled)
            : block.innerBlocks;

        return block.name === 'core/image' || block.name === 'artisanpack/image'
            ? { ...block, attributes: { ...block.attributes, _veLightbox: true }, innerBlocks }
            : { ...block, innerBlocks };
    });
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
