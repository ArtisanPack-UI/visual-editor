/**
 * PostTerms — transforms.
 *
 * Bidirectional `core/post-terms` ↔ `artisanpack/post-terms` block transforms
 * so mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/post-terms` direction is removed at the I7 cutover once
 * `core/post-terms` is no longer registered. Phase I-Block-Fork —
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
            blocks: [ 'core/post-terms' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-terms' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-terms', attributes ),
        },
    ],
};

export default transforms;
