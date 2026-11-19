/**
 * Carousel — inserter icon (#1127).
 *
 * A centred slide flanked by two partly visible ones, with a row of dots
 * underneath.
 */

import type { ReactElement } from 'react';

export default function CarouselInserterIcon(): ReactElement {
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
                d="M7 4h10a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm1 2v9h8V6H8zM2 6h2v9H2V6zm18 0h2v9h-2V6zM9 19.5a1.5 1.5 0 1 1 0 .01zm3 0a1.5 1.5 0 1 1 0 .01zm3 0a1.5 1.5 0 1 1 0 .01z"
            />
        </svg>
    );
}
