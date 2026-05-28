/**
 * Button — inserter icon.
 *
 * Inline pill SVG mirroring `@wordpress/icons`' `button` icon so the
 * editor canvas does not have to load `dashicons.css`.
 */

import type { ReactElement } from 'react';

export default function ButtonInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <rect x={3} y={9} width={18} height={6} rx={3} ry={3} />
        </svg>
    );
}
