/**
 * PostAuthorBiography — transforms.
 *
 * Bidirectional `core/post-author-biography` ↔ `artisanpack/post-author-biography`
 * block transforms so mixed documents round-trip losslessly during the V1
 * rollout. The `to: core/post-author-biography` direction is removed at
 * the I7 cutover once `core/post-author-biography` is no longer
 * registered. Author family fork (#518).
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
            blocks: [ 'core/post-author-biography' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-author-biography' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-author-biography', attributes ),
        },
    ],
};

export default transforms;
