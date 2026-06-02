/**
 * CommentContent — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Comments family fork (#519).
 */

import type { ReactElement } from 'react';

export default function CommentContentInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V6c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6v-2h12v2zm0-3H6V8h12v2z" />
        </svg>
    );
}
