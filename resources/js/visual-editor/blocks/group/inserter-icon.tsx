/**
 * Group — inserter icon.
 *
 * Inline SVG mirroring `@wordpress/icons`' `group` icon (stacked
 * rectangles within a frame) so the editor canvas does not have to
 * load `dashicons.css` to render it.
 */

import type { ReactElement } from 'react';

export default function GroupInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
        >
            <path d="M18 4H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm.5 14c0 .3-.2.5-.5.5H6c-.3 0-.5-.2-.5-.5V6c0-.3.2-.5.5-.5h12c.3 0 .5.2.5.5v12zM8 8h8v1.5H8V8zm0 3.2h8v1.5H8v-1.5zm0 3.3h5V16H8v-1.5z" />
        </svg>
    );
}
