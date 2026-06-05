/**
 * CommentDate — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A calendar glyph to differentiate from the other comment-* blocks.
 * Comments family fork (#519).
 */

import type { ReactElement } from 'react';

export default function CommentDateInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z" />
        </svg>
    );
}
