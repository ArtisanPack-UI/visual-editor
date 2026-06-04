/**
 * QueryNoResults — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Mirrors `@wordpress/icons`' `close-small` glyph, which
 * upstream uses for the no-results block. Phase I-Block-Fork query
 * family (#521).
 */

import type { ReactElement } from 'react';

export default function QueryNoResultsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z" />
        </svg>
    );
}
