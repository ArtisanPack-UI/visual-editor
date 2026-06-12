/**
 * Single Content — inserter icon.
 *
 * Inline SVG (a framed document) so the editor canvas does not have to
 * load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function SingleContentInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 2v14h14V5H5Zm2 3h10v2H7V8Zm0 4h10v2H7v-2Zm0 4h6v2H7v-2Z" />
        </svg>
    );
}
