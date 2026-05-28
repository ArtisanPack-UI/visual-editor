/**
 * Columns — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `columns` icon so the editor
 * canvas does not need to load `dashicons.css`. Two-pane grid silhouette.
 */

import type { ReactElement } from 'react';

export default function ColumnsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M20 5.5H4c-.8 0-1.5.7-1.5 1.5v10c0 .8.7 1.5 1.5 1.5h16c.8 0 1.5-.7 1.5-1.5V7c0-.8-.7-1.5-1.5-1.5zM4 7h7v10H4V7zm9 10V7h7v10h-7z" />
        </svg>
    );
}
