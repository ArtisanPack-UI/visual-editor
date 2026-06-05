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
