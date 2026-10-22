/**
 * QueryTotal — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. A tally-style glyph: a numbered badge beside list rows
 * (#962).
 */

import type { ReactElement } from 'react';

export default function QueryTotalInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 5.5h8V7H4V5.5zm0 5.75h8v1.5H4v-1.5zM4 17h8v1.5H4V17zm11.5-12h1.25v6H15.5V6.6l-1.05.45-.5-1.15L15.5 5zm-1.6 9.25c.3-.7 1-1.25 1.95-1.25 1.1 0 1.9.7 1.9 1.7 0 .6-.3 1.05-.85 1.55l-1.25 1.15h2.15V19h-4v-1.05l2.15-2c.35-.35.5-.55.5-.85 0-.35-.25-.6-.65-.6-.35 0-.6.2-.75.55l-1.15-.35z" />
        </svg>
    );
}
