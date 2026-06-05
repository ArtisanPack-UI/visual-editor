/**
 * PostFeaturedImage — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I5 entity cluster (#413).
 */

import type { ReactElement } from 'react';

export default function PostFeaturedImageInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M5 5h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm1 12h12l-4-5-3 4-2-2-3 3zM8.5 9A1.5 1.5 0 1 0 7 7.5 1.5 1.5 0 0 0 8.5 9z" />
        </svg>
    );
}
