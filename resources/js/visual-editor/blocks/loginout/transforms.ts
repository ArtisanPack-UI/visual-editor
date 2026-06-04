/**
 * Loginout — transforms.
 *
 * Bidirectional `core/loginout` ↔ `artisanpack/loginout` block transforms
 * for the V1 rollout window. The `to: core/loginout` direction is removed
 * at the I7 cutover. Phase I-Block-Fork auth (#522).
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
            blocks: [ 'core/loginout' ],
            transform: ( attributes: EntityAttributes ) => createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/loginout' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/loginout', attributes ),
        },
    ],
};

export default transforms;
