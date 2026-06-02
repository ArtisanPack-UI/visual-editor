/**
 * PostCommentsLink — transforms.
 *
 * Bidirectional `core/post-comments-link` ↔ `artisanpack/post-comments-link` block
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
            blocks: [ 'core/post-comments-link' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-comments-link' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-comments-link', attributes ),
        },
    ],
};

export default transforms;
