/**
 * SiteTitle — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I5 entity cluster (#413).
 */

import type { ReactElement } from 'react';

export default function SiteTitleInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 5h16v3H4V5zm0 6h16v2H4v-2zm0 5h10v2H4v-2z" />
        </svg>
    );
}
