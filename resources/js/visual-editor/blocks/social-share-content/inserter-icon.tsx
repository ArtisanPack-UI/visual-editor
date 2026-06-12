/**
 * Social Share Content — inserter icon.
 *
 * Inline SVG (a share-arrow glyph) so the editor canvas does not have
 * to load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function SocialShareContentInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M17 3a3 3 0 1 1-3 3l-6 3a3 3 0 1 1 0 6l6 3a3 3 0 1 1-1 2L7 14a3 3 0 1 1 0-4l6-3a3 3 0 0 1 4-4Z" />
        </svg>
    );
}
