/**
 * Avatar — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`.
 * A round portrait silhouette to convey "user avatar".
 * Author family fork (#518).
 */

import type { ReactElement } from 'react';

export default function AvatarInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 4a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm0 14a8 8 0 0 1-6.2-2.95c.03-2 4.13-3.1 6.2-3.1 2.07 0 6.17 1.1 6.2 3.1A8 8 0 0 1 12 20z" />
        </svg>
    );
}
