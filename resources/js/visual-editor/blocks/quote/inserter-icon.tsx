/**
 * Quote — inserter icon.
 *
 * Inline SVG (matches Gutenberg's `quote` icon from `@wordpress/icons`).
 */

import type { ReactElement } from 'react';

export default function QuoteInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M13 6v6h5.2L15 18h2.4l3.2-6V6H13zm-9 6h5.2L6 18h2.4l3.2-6V6H4v6z" />
        </svg>
    );
}
