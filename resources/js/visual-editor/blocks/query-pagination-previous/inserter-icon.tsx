/**
 * QueryPaginationPrevious — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Left-arrow mirroring `@wordpress/icons`' `chevronLeft`
 * glyph. Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';

export default function QueryPaginationPreviousInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z" />
        </svg>
    );
}
