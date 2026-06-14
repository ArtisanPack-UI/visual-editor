/**
 * Inline SVG separator icons shared between the breadcrumbs edit
 * component and the inserter icon.
 *
 * Inlining (instead of pulling Font Awesome like upstream did) keeps the
 * editor bundle free of an extra runtime icon dependency and guarantees
 * the saved markup is byte-identical across host environments — the same
 * paths are baked into the Blade / React / Vue renderers so authors see
 * the same chevron the public frontend renders.
 */

import type { ReactElement } from 'react';

export type SeparatorIconName =
    | 'arrow-right'
    | 'chevron-right'
    | 'chevron-double-right'
    | 'long-arrow-right';

export const SEPARATOR_ICON_NAMES: ReadonlyArray<SeparatorIconName> = [
    'arrow-right',
    'chevron-right',
    'chevron-double-right',
    'long-arrow-right',
];

export const SEPARATOR_ICON_PATHS: Readonly<Record<SeparatorIconName, string>> = {
    'arrow-right': 'M5 12h14m-6-6 6 6-6 6',
    'chevron-right': 'm9 6 6 6-6 6',
    'chevron-double-right': 'm6 6 6 6-6 6m6-12 6 6-6 6',
    'long-arrow-right': 'M3 12h17m-5-5 5 5-5 5',
};

interface SeparatorIconProps {
    readonly name: SeparatorIconName;
}

export function SeparatorIcon({ name }: SeparatorIconProps): ReactElement {
    const d = SEPARATOR_ICON_PATHS[name] ?? SEPARATOR_ICON_PATHS['chevron-right'];

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width="1em"
            height="1em"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            <path d={d} />
        </svg>
    );
}
