/**
 * Inserter icon for the `artisanpack/form` block.
 *
 * Inlined SVG (rather than a dashicon slug) so it renders without the
 * dashicons stylesheet, matching the rest of the bundled blocks.
 */

import type { ReactElement } from 'react';

export default function FormInserterIcon(): ReactElement {
    return (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm1 2v12h12V6H6zm2 2h8v2H8V8zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"
            />
        </svg>
    );
}
