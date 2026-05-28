/**
 * Separator — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `separator` icon so the editor
 * canvas does not have to load `dashicons.css` to render it.
 */

import type { ReactElement } from 'react';

export default function SeparatorInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M19 11H5v2h14v-2z" />
        </svg>
    );
}
