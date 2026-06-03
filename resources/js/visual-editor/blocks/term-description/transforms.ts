/**
 * TermDescription — transforms.
 *
 * Bidirectional `core/term-description` ↔ `artisanpack/term-description`
 * block transforms so mixed documents round-trip losslessly during the V1
 * rollout. The `to: core/term-description` direction is removed at the I7
 * cutover once `core/term-description` is no longer registered.
 * Phase I-Block-Fork — post navigation / metadata family (#520).
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
            blocks: [ 'core/term-description' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/term-description' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/term-description', attributes ),
        },
    ],
};

export default transforms;
