/**
 * PostAuthorName — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A nameplate-style glyph to differentiate from the avatar block.
 * Author family fork (#518).
 */

import type { ReactElement } from 'react';

export default function PostAuthorNameInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M5 6h14v2H5V6zm0 4h10v2H5v-2zm0 4h14v2H5v-2z" />
        </svg>
    );
}
