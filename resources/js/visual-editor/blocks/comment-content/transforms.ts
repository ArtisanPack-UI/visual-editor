/**
 * comment-content — transforms.
 *
 * Bidirectional `core/comment-content` ↔ `artisanpack/comment-content` block
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
            blocks: [ 'core/comment-content' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comment-content' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/comment-content', attributes ),
        },
    ],
};

export default transforms;
