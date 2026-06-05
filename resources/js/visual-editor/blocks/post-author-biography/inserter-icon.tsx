/**
 * PostAuthorBiography — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A document/paragraph glyph to convey "biography text".
 * Author family fork (#518).
 */

import type { ReactElement } from 'react';

export default function PostAuthorBiographyInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 4h16v2H4V4zm0 4h12v2H4V8zm0 4h16v2H4v-2zm0 4h10v2H4v-2zm0 4h14v2H4v-2z" />
        </svg>
    );
}
