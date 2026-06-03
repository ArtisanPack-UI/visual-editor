/**
 * ReadMore — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import type { ReactElement } from 'react';

export default function ReadMoreInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 6h16v1.5H4V6zm0 5.25h16v1.5H4v-1.5zM4 16.5h10V18H4v-1.5z" />
        </svg>
    );
}
