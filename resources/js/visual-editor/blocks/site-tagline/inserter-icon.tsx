/**
 * SiteTagline — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I5 entity cluster (#413).
 */

import type { ReactElement } from 'react';

export default function SiteTaglineInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 7h16v2H4V7zm0 5h12v2H4v-2zm0 4h8v2H4v-2z" />
        </svg>
    );
}
