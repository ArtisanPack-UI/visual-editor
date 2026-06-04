/**
 * Loginout — inserter icon.
 *
 * Inline SVG so the editor canvas does not have to load `dashicons.css`
 * to render it. Mirrors `@wordpress/icons`' `login` glyph (the icon
 * upstream uses for the loginout block). Phase I-Block-Fork auth (#522).
 */

import type { ReactElement } from 'react';

export default function LoginoutInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M11 14.5l1.41 1.41L16.83 11.5H4v-2h12.83l-4.42-4.41L11 6.5l-7 7 7 7v-6z" />
            <path d="M20 3h-9v2h9v14h-9v2h9c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
        </svg>
    );
}
