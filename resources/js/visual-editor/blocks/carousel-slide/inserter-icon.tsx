/**
 * Carousel slide — inserter icon (#1127).
 *
 * A single card with a dot row underneath, the active dot filled.
 */

import type { ReactElement } from 'react';

export default function CarouselSlideInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M5 3h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm1 2v10h12V5H6zM10.5 20a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm3 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm3 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"
            />
        </svg>
    );
}
