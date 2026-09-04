/**
 * Business Hours — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `clock` glyph, copied verbatim
 * from the bundled FA asset so the canvas does not need to load the Font
 * Awesome stylesheet. Font Awesome Free is (c) Fonticons, Inc., licensed
 * under CC BY 4.0. See NOTICE.md at the package root for credits.
 */

import type { ReactElement } from 'react';

export default function BusinessHoursInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M256 0a256 256 0 1 1 0 512 256 256 0 1 1 0-512zM232 120l0 136c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2 280 120c0-13.3-10.7-24-24-24s-24 10.7-24 24z" />
        </svg>
    );
}
