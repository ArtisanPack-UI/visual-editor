/**
 * Tag Cloud — transforms.
 *
 * `@wordpress/block-library/src/tag-cloud` (v9.43.0) ships a
 * `transforms.js` with a `core/categories` ↔ `core/tag-cloud` convenience
 * transform. The fork carries the cross-block convenience forward under
 * the artisanpack namespace and adds the bidirectional
 * `core/tag-cloud` ↔ `artisanpack/tag-cloud` rollout transforms so mixed
 * documents round-trip losslessly during the V1 rollout. The
 * `to: core/tag-cloud` direction is removed at the I7 cutover once
 * `core/tag-cloud` is no longer registered. Phase I6 loop / feed
 * cluster (#414).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface TagCloudAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/tag-cloud' ],
            transform: ( attributes: TagCloudAttributes ) =>
                createBlock( name, attributes ),
        },
        {
            type: 'block',
            blocks: [ 'artisanpack/categories' ],
            transform: () => createBlock( name ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/tag-cloud' ],
            transform: ( attributes: TagCloudAttributes ) =>
                createBlock( 'core/tag-cloud', attributes ),
        },
        {
            type: 'block',
            blocks: [ 'artisanpack/categories' ],
            transform: () => createBlock( 'artisanpack/categories' ),
        },
    ],
};

export default transforms;
