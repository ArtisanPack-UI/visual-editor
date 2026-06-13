/**
 * Skills Slider — inserter icon.
 *
 * Inline SVG of Font Awesome Free Solid's `bars-progress` glyph
 * (CC BY 4.0). Path data copied verbatim so the editor canvas can
 * render the icon without loading the Font Awesome stylesheet.
 */

import type { ReactElement } from 'react';

export default function SkillsSliderInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 512 512"
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d="M288 160l96 0 0-64-96 0 0 64zM0 160L0 80C0 53.5 21.5 32 48 32l352 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48L48 224c-26.5 0-48-21.5-48-48l0-16zM160 416l224 0 0-64-224 0 0 64zM0 416l0-80c0-26.5 21.5-48 48-48l352 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48L48 480c-26.5 0-48-21.5-48-48l0-16z" />
        </svg>
    );
}
