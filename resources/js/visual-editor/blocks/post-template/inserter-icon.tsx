/**
 * Post Template — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `layout` icon so the editor
 * canvas does not have to load `dashicons.css` to render it. Phase I6
 * loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';

export default function PostTemplateInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM6 5.5h12a.5.5 0 0 1 .5.5v3h-13V6a.5.5 0 0 1 .5-.5zm-.5 5H10v8H6a.5.5 0 0 1-.5-.5v-7.5zm6 8v-8h6.5V18a.5.5 0 0 1-.5.5h-6z" />
        </svg>
    );
}
