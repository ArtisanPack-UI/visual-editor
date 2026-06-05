/**
 * Paragraph — block-library inserter icon.
 *
 * Inline SVG (matches Gutenberg's `paragraph` icon from `@wordpress/icons`).
 * The editor does not bundle `dashicons.css`, so a dashicon slug in
 * `block.json` would render blank.
 */

import type { ReactElement } from 'react';

export default function ParagraphInserterIcon(): ReactElement {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width={24} height={24} aria-hidden="true">
            <path d="M9 19h1.5V5H14v14h1.5V5H19V3.5H9c-1.93 0-3.5 1.57-3.5 3.5S7.07 10.5 9 10.5V19zM9 5h3.5v4H9C7.9 9 7 8.1 7 7s.9-2 2-2z" />
        </svg>
    );
}
