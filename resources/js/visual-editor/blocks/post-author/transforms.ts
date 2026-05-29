/**
 * PostAuthor — transforms.
 *
 * Bidirectional `core/post-author` ↔ `artisanpack/post-author` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/post-author` direction is removed at the I7 cutover once
 * `core/post-author` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/post-author' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-author' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-author', attributes ),
        },
    ],
};

export default transforms;
