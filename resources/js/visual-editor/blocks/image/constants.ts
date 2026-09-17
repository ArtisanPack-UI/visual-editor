/**
 * Image — module constants.
 *
 * Ported verbatim from `@wordpress/block-library/src/image/constants.js`
 * (v9.43.0). Vendored so the fork does not reach into the upstream
 * package's internal subpaths (which are blocked by its `exports` field).
 */

export const MIN_SIZE = 20;
export const LINK_DESTINATION_NONE = 'none';
export const LINK_DESTINATION_MEDIA = 'media';
export const LINK_DESTINATION_ATTACHMENT = 'attachment';
export const LINK_DESTINATION_CUSTOM = 'custom';
export const NEW_TAB_REL: readonly string[] = ['noreferrer', 'noopener'];
export const ALLOWED_MEDIA_TYPES: readonly string[] = ['image'];
export const MEDIA_ID_NO_FEATURED_IMAGE_SET = 0;
export const SIZED_LAYOUTS: readonly string[] = ['flex', 'grid'];
export const DEFAULT_MEDIA_SIZE_SLUG = 'full';

/**
 * Delay in milliseconds before preloading an image after hovering.
 * This prevents unnecessary preloading during quick scrolling or mouse movements.
 */
export const IMAGE_PRELOAD_DELAY = 200;

/**
 * Width presets (fork-specific extension).
 *
 * Presets are stored as percentage CSS lengths so a "Medium" wordmark stays
 * visually consistent regardless of container width. `Full` writes the width
 * as `100%` — equivalent to clearing the resize but explicit in stored markup
 * so the front-end render is a single code path.
 *
 * When a custom drag lands within `WIDTH_PRESET_SNAP_PCT` of a preset value,
 * the resize handle snaps to the preset — this is what the issue calls out
 * as "snap to presets when within a small tolerance".
 */
export interface WidthPreset {
    readonly slug: 'small' | 'medium' | 'large' | 'full';
    readonly label: string;
    readonly percentage: number;
}

export const WIDTH_PRESETS: readonly WidthPreset[] = [
    { slug: 'small', label: 'S', percentage: 25 },
    { slug: 'medium', label: 'M', percentage: 50 },
    { slug: 'large', label: 'L', percentage: 75 },
    { slug: 'full', label: 'Full', percentage: 100 },
];

export const WIDTH_PRESET_SNAP_PCT = 2;

export type WidthUnit = 'px' | '%';

export const DEFAULT_WIDTH_UNIT: WidthUnit = 'px';
