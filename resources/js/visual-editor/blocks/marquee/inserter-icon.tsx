/**
 * Marquee — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `bullhorn` glyph,
 * copied verbatim from the bundled FA asset so the canvas does
 * not need to load the Font Awesome stylesheet. Font Awesome
 * Free is © Fonticons, Inc., licensed under CC BY 4.0
 * (https://creativecommons.org/licenses/by/4.0/). See
 * NOTICE.md at the package root for the full per-icon credit list.
 */

import type { ReactElement } from 'react';

export default function MarqueeInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M461.2 18.9C472.7 24 480 35.4 480 48l0 416c0 12.6-7.3 24-18.8 29.1s-24.8 3.2-34.3-5.1l-46.6-40.7c-43.6-38.1-98.7-60.3-156.4-63l0 95.7c0 17.7-14.3 32-32 32l-32 0c-17.7 0-32-14.3-32-32l0-96C57.3 384 0 326.7 0 256S57.3 128 128 128l84.5 0c61.8-.2 121.4-22.7 167.9-63.3l46.6-40.7c9.4-8.3 22.9-10.2 34.3-5.1zM224 320l0 .2c70.3 2.7 137.8 28.5 192 73.4l0-275.3c-54.2 44.9-121.7 70.7-192 73.4L224 320z" />
        </svg>
    );
}
