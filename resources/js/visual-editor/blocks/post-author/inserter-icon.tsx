/**
 * PostAuthor — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I5 entity cluster (#413).
 */

import type { ReactElement } from 'react';

export default function PostAuthorInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5z" />
        </svg>
    );
}
