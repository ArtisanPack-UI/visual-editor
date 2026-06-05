/**
 * QueryPaginationNext — transforms.
 *
 * Bidirectional `core/query-pagination-next` ↔
 * `artisanpack/query-pagination-next` block transforms for the V1
 * rollout window. Leaf block — no innerBlocks to thread. The `to:
 * core/*` direction is removed at the I7 cutover. Phase I-Block-Fork
 * query family (#521).
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
            blocks: [ 'core/query-pagination-next' ],
            transform: ( attributes: EntityAttributes ) => createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query-pagination-next' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/query-pagination-next', attributes ),
        },
    ],
};

export default transforms;
