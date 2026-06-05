/**
 * Details — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `details` icon so the editor
 * canvas does not have to load `dashicons.css` to render it.
 */

import type { ReactElement } from 'react';

export default function DetailsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path
                d="M4 16h10v1.5H4V16Zm0-4.5h16V13H4v-1.5ZM10 7h10v1.5H10V7Z"
                fillRule="evenodd"
                clipRule="evenodd"
            />
            <path d="m4 5.25 4 2.5-4 2.5v-5Z" />
        </svg>
    );
}
