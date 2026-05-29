/**
 * Archives — transforms.
 *
 * `@wordpress/block-library/src/archives` (v9.43.0) ships no
 * `transforms.js`. The fork adds bidirectional block transforms for
 * `core/archives` ↔ `artisanpack/archives` so mixed documents round-trip
 * losslessly during the V1 rollout. The `to: core/archives` direction is
 * removed at the I7 cutover once `core/archives` is no longer registered.
 * Phase I6 loop / feed cluster (#414).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface ArchivesAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/archives' ],
            transform: ( attributes: ArchivesAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/archives' ],
            transform: ( attributes: ArchivesAttributes ) =>
                createBlock( 'core/archives', attributes ),
        },
    ],
};

export default transforms;
