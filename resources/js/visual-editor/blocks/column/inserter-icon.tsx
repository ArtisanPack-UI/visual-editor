/**
 * Column — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `column` icon — a single
 * tall rectangle silhouette.
 */

import type { ReactElement } from 'react';

export default function ColumnInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M9 5.5h6c.8 0 1.5.7 1.5 1.5v10c0 .8-.7 1.5-1.5 1.5H9c-.8 0-1.5-.7-1.5-1.5V7c0-.8.7-1.5 1.5-1.5zM9 7v10h6V7H9z" />
        </svg>
    );
}
