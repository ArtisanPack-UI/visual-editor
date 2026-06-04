/**
 * QueryNoResults — transforms.
 *
 * `@wordpress/block-library/src/query-no-results` ships no `transforms.js`.
 * The fork adds bidirectional `core/query-no-results` ↔
 * `artisanpack/query-no-results` block transforms for the V1 rollout
 * window so mixed documents round-trip losslessly. The `to:
 * core/query-no-results` direction is removed at the I7 cutover once
 * `core/query-no-results` is no longer registered. Phase I-Block-Fork
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
            blocks: [ 'core/query-no-results' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/query-no-results' ],
            transform: (
                attributes: EntityAttributes,
                innerBlocks: InnerBlocks = []
            ) => createBlock( 'core/query-no-results', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
