/**
 * PostNavigationLink — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import type { ReactElement } from 'react';

export default function PostNavigationLinkInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M14.6 7L13.5 8.1l2.9 2.9H4v1.5h12.4l-2.9 2.9 1.1 1.1 4.7-4.7L14.6 7z" />
        </svg>
    );
}
