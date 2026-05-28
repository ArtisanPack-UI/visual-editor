/**
 * Heading — inserter icon.
 *
 * Inline SVG (matches Gutenberg's `heading` icon from `@wordpress/icons`).
 */

import type { ReactElement } from 'react';

export default function HeadingInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M6.2 5.2v13.4l5.8-4.8 5.8 4.8V5.2z" />
        </svg>
    );
}
