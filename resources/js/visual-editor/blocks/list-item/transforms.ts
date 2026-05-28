/**
 * List item — transforms.
 *
 * Ported from `@wordpress/block-library/src/list-item/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/list-item` ↔ `artisanpack/list-item`.
 */

import { createBlock, cloneBlock } from '@wordpress/blocks';
// `cloneBlock` accepts the public WP block instance shape — we type the
// inner-blocks argument with that import so the call site doesn't need a
// suppressing cast.
import type { BlockInstance } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface ListItemAttributes {
    content?: string;
    [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/list-item'],
            transform: (
                attributes: ListItemAttributes,
                innerBlocks: BlockInstance[] = []
            ) => createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/paragraph'],
            transform: (
                attributes: ListItemAttributes,
                innerBlocks: BlockInstance[] = []
            ) => [
                createBlock('core/paragraph', attributes),
                ...innerBlocks.map((block) => cloneBlock(block)),
            ],
        },
        {
            type: 'block',
            blocks: ['core/list-item'],
            transform: (
                attributes: ListItemAttributes,
                innerBlocks: BlockInstance[] = []
            ) => createBlock('core/list-item', attributes, innerBlocks),
        },
    ],
};

export default transforms;
