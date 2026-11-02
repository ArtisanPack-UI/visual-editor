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
        ...['core/paragraph', 'artisanpack/paragraph'].map((blockName) => ({
            type: 'block' as const,
            blocks: [blockName],
            transform: (attributes: VerseAttributes) =>
                createBlock(blockName, attributes),
        })),
        {
            type: 'block',
            blocks: ['core/verse'],
            transform: (attributes: VerseAttributes) =>
                createBlock('core/verse', attributes),
        },
    ],
};

export default transforms;
