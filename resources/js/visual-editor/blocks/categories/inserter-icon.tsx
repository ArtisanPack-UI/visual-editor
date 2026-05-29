/**
 * Categories — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `category` icon so the editor
 * canvas does not have to load `dashicons.css` to render it. Phase I6
 * loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';

export default function CategoriesInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M6 5.5h12a.5.5 0 0 1 .5.5v3H5.5V6a.5.5 0 0 1 .5-.5ZM4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Zm1.5 4.5h13V18a.5.5 0 0 1-.5.5H6a.5.5 0 0 1-.5-.5v-7.5ZM7 13h6v1.5H7V13Zm0 3h6v1.5H7V16Z" />
        </svg>
    );
}
