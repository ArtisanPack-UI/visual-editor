/**
 * QueryTitle — transforms.
 *
 * Bidirectional `core/query-title` ↔ `artisanpack/query-title` block
 * transforms for the V1 rollout window. The `to: core/query-title`
 * direction is removed at the I7 cutover. Phase I-Block-Fork query
 * family (#521).
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
            blocks: [ 'core/query-title' ],
            transform: ( attributes: EntityAttributes ) => createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query-title' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/query-title', attributes ),
        },
    ],
};

export default transforms;
