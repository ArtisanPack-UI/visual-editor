/**
 * Grid — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `table-cells` glyph,
 * copied verbatim from the bundled FA asset so the canvas does
 * not need to load the Font Awesome stylesheet. Font Awesome
 * Free is © Fonticons, Inc., licensed under CC BY 4.0
 * (https://creativecommons.org/licenses/by/4.0/). See
 * NOTICE.md at the package root for the full per-icon credit list.
 */

import type { ReactElement } from 'react';

export default function GridInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M384 96l0 64-64 0 0-64 64 0zm0 128l0 64-64 0 0-64 64 0zm0 128l0 64-64 0 0-64 64 0zM256 288l-64 0 0-64 64 0 0 64zm-64 64l64 0 0 64-64 0 0-64zm-64-64l-64 0 0-64 64 0 0 64zM64 352l64 0 0 64-64 0 0-64zm0-192l0-64 64 0 0 64-64 0zm128 0l0-64 64 0 0 64-64 0zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32z" />
        </svg>
    );
}
