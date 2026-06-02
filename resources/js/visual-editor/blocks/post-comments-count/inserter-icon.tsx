/**
 * PostCommentsCount — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A "#" glyph signaling a count. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';

export default function PostCommentsCountInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M19.31 8 17.79 6 14.93 8.74 12.65 6 11.13 8 13.4 10.74 11.13 13.74 12.65 15.74 14.93 13 17.79 15.74 19.31 13.74 17.04 11 19.31 8zM8 16h2v3h2v-3h2v-2H8v2zM4 8h2v6h2V8h2V6H4v2z" />
        </svg>
    );
}
