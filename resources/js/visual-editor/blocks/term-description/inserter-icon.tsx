/**
 * TermDescription — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import type { ReactElement } from 'react';

export default function TermDescriptionInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 6h12v1.5H4V6zm0 4h16v1.5H4V10zm0 4h16v1.5H4V14zm0 4h12v1.5H4V18z" />
        </svg>
    );
}
