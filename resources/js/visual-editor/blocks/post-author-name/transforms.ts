/**
 * PostAuthorName — transforms.
 *
 * Bidirectional `core/post-author-name` ↔ `artisanpack/post-author-name`
 * block transforms so mixed documents round-trip losslessly during the V1
 * rollout. The `to: core/post-author-name` direction is removed at the I7
 * cutover once `core/post-author-name` is no longer registered. Author
 * family fork (#518).
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
            blocks: [ 'core/post-author-name' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-author-name' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-author-name', attributes ),
        },
    ],
};

export default transforms;
