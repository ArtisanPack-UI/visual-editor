/**
 * CommentsPaginationPrevious — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A left-pointing chevron (previous page). Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';

export default function CommentsPaginationPreviousInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
        </svg>
    );
}
