/**
 * Social Icon — service glyph (#1023).
 *
 * Mirrors the `<svg>` the renderers emit so the canvas and the front end
 * show the same icon. Sized by the block-library CSS (`1em`).
 */

import type { ReactElement } from 'react';

import { getSocialLinkGlyph } from './utils';

export default function ServiceIcon({ service }: { readonly service: string }): ReactElement {
    const { viewBox, path } = getSocialLinkGlyph(service);

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox={viewBox}
            width={24}
            height={24}
            aria-hidden="true"
            focusable="false"
        >
            <path fill="currentColor" d={path} />
        </svg>
    );
}
