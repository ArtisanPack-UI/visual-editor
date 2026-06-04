/**
 * QueryPaginationNumbers — transforms.
 *
 * Bidirectional `core/query-pagination-numbers` ↔
 * `artisanpack/query-pagination-numbers` block transforms for the V1
 * rollout window. The `to: core/*` direction is removed at the I7
 * cutover. Phase I-Block-Fork query family (#521).
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
            blocks: [ 'core/query-pagination-numbers' ],
            transform: ( attributes: EntityAttributes ) => createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query-pagination-numbers' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/query-pagination-numbers', attributes ),
        },
    ],
};

export default transforms;
