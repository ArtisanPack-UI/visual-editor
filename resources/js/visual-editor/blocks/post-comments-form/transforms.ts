/**
 * PostCommentsForm — transforms.
 *
 * Bidirectional `core/post-comments-form` ↔ `artisanpack/post-comments-form` block
 * transforms for the V1 rollout window. Comments family fork (#519) Pass 2.
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
            blocks: [ 'core/post-comments-form' ],
            // Wrapper block — thread innerBlocks through so the nested
            // tree round-trips losslessly through the namespace swap.
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-comments-form' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( 'core/post-comments-form', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
