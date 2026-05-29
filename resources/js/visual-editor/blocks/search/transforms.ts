/**
 * Search — transforms.
 *
 * `@wordpress/block-library/src/search` (v9.43.0) ships no `transforms.js`.
 * The fork adds bidirectional block transforms for
 * `core/search` ↔ `artisanpack/search` so mixed documents round-trip
 * losslessly during the V1 rollout. The `to: core/search` direction is
 * removed at the I7 cutover once `core/search` is no longer registered.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface SearchAttributes {
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/search' ],
            transform: ( attributes: SearchAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/search' ],
            transform: ( attributes: SearchAttributes ) =>
                createBlock( 'core/search', attributes ),
        },
    ],
};

export default transforms;
