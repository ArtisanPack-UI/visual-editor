/**
 * PostContent — transforms.
 *
 * Bidirectional `core/post-content` ↔ `artisanpack/post-content` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/post-content` direction is removed at the I7 cutover once
 * `core/post-content` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/post-content' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-content' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-content', attributes ),
        },
    ],
};

export default transforms;
