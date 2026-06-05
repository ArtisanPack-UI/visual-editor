/**
 * Archives — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `archive` icon so the editor
 * canvas does not have to load `dashicons.css` to render it. Phase I6
 * loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';

export default function ArchivesInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M19 4H5a2 2 0 0 0-2 2v1.5a1 1 0 0 0 1 1h.5V18a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8.5h.5a1 1 0 0 0 1-1V6a2 2 0 0 0-2-2Zm-.5 14a.5.5 0 0 1-.5.5H6a.5.5 0 0 1-.5-.5V8.5h13V18ZM20 7H4V6a.5.5 0 0 1 .5-.5h15a.5.5 0 0 1 .5.5v1ZM9 11h6v1.5H9V11Z" />
        </svg>
    );
}
