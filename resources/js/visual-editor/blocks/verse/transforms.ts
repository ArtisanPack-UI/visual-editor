/**
 * Verse — transforms.
 *
 * Ported from `@wordpress/block-library/src/verse/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/verse` ↔
 * `artisanpack/verse`.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface VerseAttributes {
    content?: string;
    [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/paragraph', 'artisanpack/paragraph'],
            transform: (attributes: VerseAttributes) =>
                createBlock(name, attributes),
        },
        {
            type: 'block',
            blocks: ['core/verse'],
            transform: (attributes: VerseAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/paragraph'],
            transform: (attributes: VerseAttributes) =>
                createBlock('core/paragraph', attributes),
        },
        {
            type: 'block',
            blocks: ['core/verse'],
            transform: (attributes: VerseAttributes) =>
                createBlock('core/verse', attributes),
        },
    ],
};

export default transforms;
