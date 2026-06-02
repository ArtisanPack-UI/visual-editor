/**
 * comment-author-name — transforms.
 *
 * Bidirectional `core/comment-author-name` ↔ `artisanpack/comment-author-name` block
 * transforms for the V1 rollout window. Comments family fork (#519).
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
            blocks: [ 'core/comment-author-name' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comment-author-name' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/comment-author-name', attributes ),
        },
    ],
};

export default transforms;
