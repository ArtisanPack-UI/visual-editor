/**
 * Post Variant — inserter icon. Stacked-layers glyph signalling the
 * override-template nature of the block.
 */

import type { ReactElement } from 'react';

export default function PostVariantInserterIcon(): ReactElement {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width={ 24 }
            height={ 24 }
            aria-hidden="true"
        >
            <path d="M4 8l8-4 8 4-8 4-8-4zm0 4l8 4 8-4M4 16l8 4 8-4" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" strokeLinecap="round" />
        </svg>
    );
}
