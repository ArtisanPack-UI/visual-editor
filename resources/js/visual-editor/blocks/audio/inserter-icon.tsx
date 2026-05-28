/**
 * Audio — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `audio` icon so the editor
 * canvas does not have to load `dashicons.css` to render it.
 */

import type { ReactElement } from 'react';

export default function AudioInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M12 3.3c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16C7.9 19.3 4.8 16.1 4.8 12S8 4.8 12 4.8c4.1 0 7.2 3.2 7.2 7.2 0 4-3.1 7.3-7.2 7.3zM11 17l6-5-6-5v10z" />
        </svg>
    );
}
