/**
 * QueryPagination — transforms.
 *
 * Bidirectional `core/query-pagination` ↔ `artisanpack/query-pagination`
 * block transforms for the V1 rollout window. Wrapper variant threads
 * `innerBlocks` through `createBlock` so the nested previous / numbers /
 * next tree survives the namespace round-trip. The `to:
 * core/query-pagination` direction is removed at the I7 cutover once
 * `core/query-pagination` is no longer registered. Phase I-Block-Fork
 * query family (#521).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface EntityAttributes {
    readonly [ key: string ]: unknown;
}

type InnerBlocks = Parameters<typeof createBlock>[ 2 ];

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/query-pagination' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query-pagination' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( 'core/query-pagination', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
