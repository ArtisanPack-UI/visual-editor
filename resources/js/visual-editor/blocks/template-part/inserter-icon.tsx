/**
 * TemplatePart — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * Phase I5 entity cluster (#413).
 */

import type { ReactElement } from 'react';

export default function TemplatePartInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M19 5H5v3h14V5zm0 5h-6v9h6v-9zM5 19h6v-9H5v9z" />
        </svg>
    );
}
