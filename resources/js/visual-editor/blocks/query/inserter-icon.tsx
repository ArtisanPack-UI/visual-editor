/**
 * Query Loop — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `loop` icon so the editor canvas
 * does not have to load `dashicons.css` to render it. Phase I6 loop / feed
 * cluster (#414).
 */

import type { ReactElement } from 'react';

export default function QueryInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M19.5 4.5h-15a1 1 0 0 0-1 1V11h1.5V6h14v5H6.31l1.72-1.72-1.06-1.06L3.19 11.5l3.78 3.78 1.06-1.06L6.31 12.5H19.5a1 1 0 0 0 1-1V5.5a1 1 0 0 0-1-1Zm-1 14v-3H17v3H4.5V20h14a1 1 0 0 0 1-1v-.5h-1Z" />
        </svg>
    );
}
