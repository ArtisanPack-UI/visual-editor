/**
 * PostCommentsForm — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A form/lines-with-textbox glyph. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';

export default function PostCommentsFormInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M20 2H4c-1.11 0-2 .9-2 2v18l4-4h14c1.11 0 2-.9 2-2V4c0-1.11-.89-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z" />
        </svg>
    );
}
