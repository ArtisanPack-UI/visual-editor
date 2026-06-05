/**
 * CommentsPagination — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A paired-chevron glyph (page-back / page-forward) to differentiate
 * from the rest of the comments family. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';

export default function CommentsPaginationInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12zM20 6h-2v12h2zM4 6h2v12H4z" />
        </svg>
    );
}
