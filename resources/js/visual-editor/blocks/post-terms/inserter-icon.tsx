/**
 * PostTerms — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import type { ReactElement } from 'react';

export default function PostTermsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M20.1 11.2l-6.7-6.7c-.1-.1-.3-.2-.5-.2H5c-.4 0-.8.3-.8.8v8c0 .2.1.4.2.5l6.7 6.7c.3.3.8.3 1.1 0l7.9-7.9c.3-.4.3-.9 0-1.2zm-8.5 7.4L5.7 12.7V5.7h7l5.9 5.9-7 7z" />
            <circle cx="8.5" cy="8.5" r="1.2" />
        </svg>
    );
}
