/**
 * Column — transforms.
 *
 * Upstream `@wordpress/block-library/src/column/` ships no transforms.js
 * (column is a child-only block). Added here purely as a bidirectional
 * `core/column` ↔ `artisanpack/column` round-trip so mixed documents
 * survive paste and conversion during the V2 rollout.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/column'],
            transform: (attributes: any, innerBlocks: any[]) =>
                createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/column'],
            transform: (attributes: any, innerBlocks: any[]) =>
                createBlock('core/column', attributes, innerBlocks),
        },
    ],
};

export default transforms;
