/**
 * Media & Text — constants.
 *
 * Ported from `@wordpress/block-library/src/media-text/constants.js`
 * (v9.43.0). Values are intentionally byte-equivalent so the editor and
 * the deprecation chain reference the same source of truth.
 */

import { _x } from '@wordpress/i18n';

export const DEFAULT_MEDIA_SIZE_SLUG = 'full';
export const WIDTH_CONSTRAINT_PERCENTAGE = 15;
export const LINK_DESTINATION_NONE = 'none';
export const LINK_DESTINATION_MEDIA = 'media';
export const LINK_DESTINATION_ATTACHMENT = 'attachment';

export const TEMPLATE: ReadonlyArray<readonly [string, Record<string, unknown>]> = [
    [
        'core/paragraph',
        {
            placeholder: _x('Content…', 'content placeholder'),
        },
    ],
];
