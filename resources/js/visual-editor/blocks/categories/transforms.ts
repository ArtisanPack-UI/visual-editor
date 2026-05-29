/**
 * Categories — transforms.
 *
 * `@wordpress/block-library/src/categories` (v9.43.0) ships a
 * `variations.js` but no `transforms.js`. The fork adds bidirectional
 * block transforms for `core/categories` ↔ `artisanpack/categories` so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/categories` direction is removed at the I7 cutover once
 * `core/categories` is no longer registered. Phase I6 loop / feed
 * cluster (#414).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface CategoriesAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/categories' ],
            transform: ( attributes: CategoriesAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/categories' ],
            transform: ( attributes: CategoriesAttributes ) =>
                createBlock( 'core/categories', attributes ),
        },
    ],
};

export default transforms;
