/**
 * Buttons — inserter icon.
 *
 * Inline SVG showing two stacked pill shapes, mirroring `@wordpress/icons`'
 * `buttons` icon so the editor canvas does not have to load `dashicons.css`.
 */

import type { ReactElement } from 'react';

export default function ButtonsInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <rect x={3} y={5} width={18} height={6} rx={3} ry={3} />
            <rect
                x={3}
                y={13}
                width={18}
                height={6}
                rx={3}
                ry={3}
                fill="none"
                stroke="currentColor"
                strokeWidth={1.5}
            />
        </svg>
    );
}
