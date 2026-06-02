/**
 * CommentsPaginationPrevious — transforms.
 *
 * Bidirectional `core/comments-pagination-previous` ↔ `artisanpack/comments-pagination-previous` block
 * transforms for the V1 rollout window. Comments family fork (#519) Pass 2.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface EntityAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/comments-pagination-previous' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comments-pagination-previous' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/comments-pagination-previous', attributes ),
        },
    ],
};

export default transforms;
