/**
 * QueryPagination — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Mirrors `@wordpress/icons`' `queryPagination` glyph.
 * Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';

export default function QueryPaginationInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 6h16v1.5H4V6zm0 12h7v-1.5H4V18zm9-3h7v-1.5h-7V15zm-9 0h7v-1.5H4V15zm9-3h7v-1.5h-7V12zm-9 0h7v-1.5H4V12zm9-3h7V7.5h-7V9zm-9 0h7V7.5H4V9z" />
        </svg>
    );
}
