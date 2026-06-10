/**
 * Icon — inserter icon.
 *
 * A generic star-burst glyph so the block reads as "decorative icon"
 * at a glance in the inserter grid. Resolved-icon previews are the
 * picker's job (#555), not the inserter's.
 */

import type { ReactElement } from 'react';

export default function IconInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M12 2l2.4 6.6L21 9.3l-5 4.3L17.6 21 12 17.3 6.4 21 8 13.6 3 9.3l6.6-.7L12 2z" />
        </svg>
    );
}
