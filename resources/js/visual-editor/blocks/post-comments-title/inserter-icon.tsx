/**
 * PostCommentsTitle — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A heading-style "H" glyph. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';

export default function PostCommentsTitleInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M5 4v3h5.5v12h3V7H19V4z" />
        </svg>
    );
}
