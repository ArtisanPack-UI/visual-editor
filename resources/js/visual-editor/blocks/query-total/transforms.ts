/**
 * QueryTotal — transforms.
 *
 * Bidirectional `core/query-total` ↔ `artisanpack/query-total` block
 * transforms, matching the rest of the query family (#962).
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
            blocks: [ 'core/query-total' ],
            transform: ( attributes: EntityAttributes ) => createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query-total' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/query-total', attributes ),
        },
    ],
};

export default transforms;
