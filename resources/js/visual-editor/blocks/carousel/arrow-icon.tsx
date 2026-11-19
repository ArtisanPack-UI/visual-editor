/**
 * Carousel — arrow glyph (#1127).
 *
 * A plain chevron drawn with `currentColor`, so the arrow colour control
 * (and any theme rule on the button's `color`) styles it. The Blade, React
 * and Vue renderers print the same SVG.
 */

import type { ReactElement } from 'react';

export const ARROW_PATHS = {
    previous: 'M15 6l-6 6 6 6',
    next: 'M9 6l6 6-6 6',
} as const;

export function ArrowIcon({ direction }: { readonly direction: keyof typeof ARROW_PATHS }): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path
                d={ARROW_PATHS[direction]}
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
