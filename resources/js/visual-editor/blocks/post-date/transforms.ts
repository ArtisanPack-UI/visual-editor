/**
 * PostDate — transforms.
 *
 * Bidirectional `core/post-date` ↔ `artisanpack/post-date` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/post-date` direction is removed at the I7 cutover once
 * `core/post-date` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/post-date' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-date' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-date', attributes ),
        },
    ],
};

export default transforms;
