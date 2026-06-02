/**
 * comment-date — transforms.
 *
 * Bidirectional `core/comment-date` ↔ `artisanpack/comment-date` block
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
            blocks: [ 'core/comment-date' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/comment-date' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/comment-date', attributes ),
        },
    ],
};

export default transforms;
