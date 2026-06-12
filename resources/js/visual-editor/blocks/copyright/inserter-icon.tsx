/**
 * Copyright — inserter icon.
 *
 * Inline SVG (a circled "C") so the editor canvas does not have to load
 * `dashicons.css` for the block-library inserter preview.
 */

import type { ReactElement } from 'react';

export default function CopyrightInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8Zm0-13a5 5 0 0 0 0 10 5 5 0 0 0 3.54-1.46l-1.42-1.42A3 3 0 1 1 12 9a3 3 0 0 1 2.12.88l1.42-1.42A5 5 0 0 0 12 7Z" />
        </svg>
    );
}
