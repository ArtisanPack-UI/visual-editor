/**
 * Author Social Icons — inserter icon.
 *
 * Inline SVG (a person silhouette inside a circle) so the editor canvas
 * does not have to load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function AuthorSocialIconsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm0 2a7 7 0 0 0-5 12 5 5 0 0 1 10 0 7 7 0 0 0-5-12Zm0 3a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z" />
        </svg>
    );
}
