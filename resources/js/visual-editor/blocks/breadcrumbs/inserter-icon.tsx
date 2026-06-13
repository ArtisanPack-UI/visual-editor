/**
 * Breadcrumbs — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `signs-post` glyph,
 * copied verbatim from the bundled FA asset so the canvas does
 * not need to load the Font Awesome stylesheet. Font Awesome
 * Free is © Fonticons, Inc., licensed under CC BY 4.0
 * (https://creativecommons.org/licenses/by/4.0/). See
 * NOTICE.md at the package root for the full per-icon credit list.
 */

import type { ReactElement } from 'react';

export default function BreadcrumbsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M256.4 0c-17.7 0-32 14.3-32 32l0 32-160 0c-17.7 0-32 14.3-32 32l0 64c0 17.7 14.3 32 32 32l160 0 0 64-153.4 0c-4.2 0-8.3 1.7-11.3 4.7l-48 48c-6.2 6.2-6.2 16.4 0 22.6l48 48c3 3 7.1 4.7 11.3 4.7l153.4 0 0 96c0 17.7 14.3 32 32 32s32-14.3 32-32l0-96 160 0c17.7 0 32-14.3 32-32l0-64c0-17.7-14.3-32-32-32l-160 0 0-64 153.4 0c4.2 0 8.3-1.7 11.3-4.7l48-48c6.2-6.2 6.2-16.4 0-22.6l-48-48c-3-3-7.1-4.7-11.3-4.7l-153.4 0 0-32c0-17.7-14.3-32-32-32z" />
        </svg>
    );
}
