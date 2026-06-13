/**
 * Grid Item — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `square` glyph
 * (CC BY 4.0). Path data copied verbatim so the editor canvas can
 * render the icon without loading the Font Awesome stylesheet.
 */

import type { ReactElement } from 'react';

export default function GridItemInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M64 32l320 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96C0 60.7 28.7 32 64 32z" />
        </svg>
    );
}
