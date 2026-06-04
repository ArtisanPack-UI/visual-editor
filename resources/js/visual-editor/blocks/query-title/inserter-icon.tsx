/**
 * QueryTitle — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Mirrors `@wordpress/icons`' `heading` glyph (the icon
 * upstream uses for the query-title block). Phase I-Block-Fork query
 * family (#521).
 */

import type { ReactElement } from 'react';

export default function QueryTitleInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M6 5h2v6h7V5h2v14h-2v-6H8v6H6V5z" />
        </svg>
    );
}
