/**
 * Media & Text — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `mediaAndText` icon so the
 * editor canvas does not have to load `dashicons.css` to render it.
 */

import type { ReactElement } from 'react';

export default function MediaTextInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M3 6v11.5h8V6H3Zm11 3h7V7.5h-7V9Zm7 3.5h-7V11h7v1.5ZM14 16h7v-1.5h-7V16Z" />
        </svg>
    );
}
