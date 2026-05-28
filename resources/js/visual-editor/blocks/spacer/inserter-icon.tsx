/**
 * Spacer — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `resizeCornerNE`/spacer-style
 * icon so the editor canvas does not have to load `dashicons.css` to
 * render it.
 */

import type { ReactElement } from 'react';

export default function SpacerInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M13 6.5h-2v11h2v-11zM5 11h14v2H5v-2z" opacity=".25" />
            <path d="M19 11H5v2h14v-2z" />
        </svg>
    );
}
