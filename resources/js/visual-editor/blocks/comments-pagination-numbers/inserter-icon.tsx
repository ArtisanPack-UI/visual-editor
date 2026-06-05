/**
 * CommentsPaginationNumbers — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A "1 2 3" page-number glyph. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';

export default function CommentsPaginationNumbersInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M3 17h2v2H3zm0-7h2v5H3zm4-3h2v12H7zm8 9h2v2h-2zm0-7h2v5h-2zm-4 0h2v9h-2zm8-3h2v12h-2z" />
        </svg>
    );
}
