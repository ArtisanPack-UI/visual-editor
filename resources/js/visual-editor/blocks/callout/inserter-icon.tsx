/**
 * Inline SVG icon used by the block-library inserter.
 *
 * Mirrors the lightbulb glyph rather than the severity glyph — the
 * inserter icon should communicate "tip / highlight," not a specific
 * severity state the author hasn't chosen yet.
 */

import type { ReactElement } from 'react';

export default function CalloutInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width="24"
            height="24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M9 21h6v-1H9Zm3-19a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2Zm1 12h-2v-2h2Z" />
        </svg>
    );
}
