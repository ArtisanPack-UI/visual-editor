/**
 * Search Filters — inserter icon.
 *
 * Inline SVG (a funnel) so the canvas does not have to load
 * `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function SearchFiltersInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M3 4h18v2l-7 8v6l-4-2v-4L3 6V4z" />
        </svg>
    );
}
