/**
 * Social Share Content — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `share-nodes` glyph,
 * copied verbatim from the bundled FA asset so the canvas does
 * not need to load the Font Awesome stylesheet. Font Awesome
 * Free is © Fonticons, Inc., licensed under CC BY 4.0
 * (https://creativecommons.org/licenses/by/4.0/). See
 * NOTICE.md at the package root for the full per-icon credit list.
 */

import type { ReactElement } from 'react';

export default function SocialShareContentInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M384 192c53 0 96-43 96-96s-43-96-96-96-96 43-96 96c0 5.4 .5 10.8 1.3 16L159.6 184.1c-16.9-15-39.2-24.1-63.6-24.1-53 0-96 43-96 96s43 96 96 96c24.4 0 46.6-9.1 63.6-24.1L289.3 400c-.9 5.2-1.3 10.5-1.3 16 0 53 43 96 96 96s96-43 96-96-43-96-96-96c-24.4 0-46.6 9.1-63.6 24.1L190.7 272c.9-5.2 1.3-10.5 1.3-16s-.5-10.8-1.3-16l129.7-72.1c16.9 15 39.2 24.1 63.6 24.1z" />
        </svg>
    );
}
