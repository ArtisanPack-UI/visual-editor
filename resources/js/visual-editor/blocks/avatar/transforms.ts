/**
 * Avatar — transforms.
 *
 * Bidirectional `core/avatar` ↔ `artisanpack/avatar` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/avatar` direction is removed at the I7 cutover once
 * `core/avatar` is no longer registered. Author family fork (#518).
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
            blocks: [ 'core/avatar' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/avatar' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/avatar', attributes ),
        },
    ],
};

export default transforms;
