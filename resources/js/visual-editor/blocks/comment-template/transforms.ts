/**
 * CommentTemplate — transforms.
 *
 * Bidirectional `core/comment-template` ↔ `artisanpack/comment-template`
 * block transforms for the V1 rollout window. Comments family fork (#519).
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
            blocks: [ 'core/comment-template' ],
            // Loop wrapper — pass innerBlocks through so the per-iteration
            // template (the leaf comment-* blocks) is preserved across the
            // namespace swap.
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comment-template' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( 'core/comment-template', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
