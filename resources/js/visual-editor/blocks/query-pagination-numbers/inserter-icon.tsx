/**
 * QueryPaginationNumbers — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Mirrors `@wordpress/icons`' `paginationNumbers`-like
 * glyph. Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';

export default function QueryPaginationNumbersInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M3.5 6h17v1.5h-17V6zm0 5.25h17v1.5h-17v-1.5zm0 5.25h17V18h-17v-1.5z" />
        </svg>
    );
}
