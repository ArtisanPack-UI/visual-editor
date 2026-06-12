/**
 * Skills Slider — inserter icon.
 *
 * Inline SVG (a filled progress bar) so the editor canvas does not
 * have to load `dashicons.css` for the inserter preview.
 */

import type { ReactElement } from 'react';

export default function SkillsSliderInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <rect x="2" y="10" width="20" height="4" rx="2" fill="currentColor" opacity="0.25" />
            <rect x="2" y="10" width="12" height="4" rx="2" fill="currentColor" />
        </svg>
    );
}
