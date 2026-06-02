/**
 * Comments — transforms.
 *
 * Bidirectional `core/comments` ↔ `artisanpack/comments` block
 * transforms so mixed documents round-trip losslessly during the V1
 * rollout. The `to: core/comments` direction is removed at the I7
 * cutover once `core/comments` is no longer registered. Comments
 * family fork (#519).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface EntityAttributes {
    readonly [ key: string ]: unknown;
}

type InnerBlocks = Parameters<typeof createBlock>[ 2 ];

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/comments' ],
            // Wrapper block — pass innerBlocks through so the nested
            // comment-template (and any post-level comments metadata)
            // tree round-trips losslessly.
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comments' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( 'core/comments', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
