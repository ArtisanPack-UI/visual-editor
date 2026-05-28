/**
 * List item — inserter icon.
 */

import type { ReactElement } from 'react';

export default function ListItemInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <circle cx="4" cy="12" r="2" />
            <path d="M8 11h12v2H8z" />
        </svg>
    );
}
