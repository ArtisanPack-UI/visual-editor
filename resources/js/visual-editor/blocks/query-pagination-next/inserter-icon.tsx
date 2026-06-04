/**
 * QueryPaginationNext — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Right-arrow mirroring `@wordpress/icons`' `chevronRight`
 * glyph. Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';

export default function QueryPaginationNextInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M10.6 6L9.4 7l4.6 5-4.6 5 1.2 1 5.4-6z" />
        </svg>
    );
}
