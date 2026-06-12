/**
 * Related Posts — inserter icon.
 *
 * Inline SVG (a stack of cards) so the editor canvas does not have to
 * load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function RelatedPostsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M4 4h16v3H4V4Zm0 6h16v3H4v-3Zm0 6h16v3H4v-3Z" />
        </svg>
    );
}
