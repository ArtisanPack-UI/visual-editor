/**
 * Comments Number — inserter icon.
 *
 * Inline SVG (a numbered speech bubble) so the editor canvas does not
 * have to load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function CommentsNumberInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M4 3h16a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H8l-4 4V4a1 1 0 0 1 1-1Zm1 2v13l2-2h13V5H5Zm6 3h2v6h-2V8Z" />
        </svg>
    );
}
