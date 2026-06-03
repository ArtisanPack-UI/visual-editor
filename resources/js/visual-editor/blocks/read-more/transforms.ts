/**
 * ReadMore — transforms.
 *
 * Bidirectional `core/read-more` ↔ `artisanpack/read-more` block transforms
 * so mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/read-more` direction is removed at the I7 cutover once
 * `core/read-more` is no longer registered. Phase I-Block-Fork —
 * post navigation / metadata family (#520).
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
            blocks: [ 'core/read-more' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/read-more' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/read-more', attributes ),
        },
    ],
};

export default transforms;
