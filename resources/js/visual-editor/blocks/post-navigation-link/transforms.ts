/**
 * PostNavigationLink — transforms.
 *
 * Bidirectional `core/post-navigation-link` ↔ `artisanpack/post-navigation-link`
 * block transforms so mixed documents round-trip losslessly during the V1
 * rollout. The `to: core/post-navigation-link` direction is removed at the
 * I7 cutover once `core/post-navigation-link` is no longer registered.
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
            blocks: [ 'core/post-navigation-link' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-navigation-link' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-navigation-link', attributes ),
        },
    ],
};

export default transforms;
