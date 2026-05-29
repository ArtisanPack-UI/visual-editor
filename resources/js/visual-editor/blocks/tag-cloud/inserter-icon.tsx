/**
 * Tag Cloud — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `tag` icon so the editor canvas
 * does not have to load `dashicons.css` to render it. Phase I6 loop / feed
 * cluster (#414).
 */

import type { ReactElement } from 'react';

export default function TagCloudInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3H4a1 1 0 0 0-1 1v5.59A2 2 0 0 0 3.59 11l9.58 9.59a2 2 0 0 0 2.83 0l4.59-4.59a2 2 0 0 0 0-2.59ZM7 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z" />
        </svg>
    );
}
