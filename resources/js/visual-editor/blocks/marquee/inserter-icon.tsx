/**
 * Marquee — inserter icon.
 *
 * Inline SVG (a megaphone-ish horizontal arrow) so the editor canvas
 * does not have to load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function MarqueeInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M3 6h18v12H3V6Zm2 2v8h14V8H5Zm2 1h6v1H7V9Zm0 2h10v1H7v-1Zm0 2h6v1H7v-1Z" />
        </svg>
    );
}
