/**
 * CommentsPaginationNext — transforms.
 *
 * Bidirectional `core/comments-pagination-next` ↔ `artisanpack/comments-pagination-next` block
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
            blocks: [ 'core/comments-pagination-next' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comments-pagination-next' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/comments-pagination-next', attributes ),
        },
    ],
};

export default transforms;
