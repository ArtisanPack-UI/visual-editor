/**
 * Breadcrumbs — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A trail-of-pills shape reads as "breadcrumbs" at the inserter's small
 * preview size.
 */

import type { ReactElement } from 'react';

export default function BreadcrumbsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M3 10h4v4H3v-4zm5 2 2-2v4l-2-2zm3-2h4v4h-4v-4zm5 2 2-2v4l-2-2zm3-2h4v4h-4v-4z" />
        </svg>
    );
}
