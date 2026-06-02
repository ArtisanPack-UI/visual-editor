/**
 * comment-reply-link — transforms.
 *
 * Bidirectional `core/comment-reply-link` ↔ `artisanpack/comment-reply-link` block
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
            blocks: [ 'core/comment-reply-link' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comment-reply-link' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/comment-reply-link', attributes ),
        },
    ],
};

export default transforms;
