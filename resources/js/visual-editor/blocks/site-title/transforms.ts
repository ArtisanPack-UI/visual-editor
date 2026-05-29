/**
 * SiteTitle — transforms.
 *
 * Bidirectional `core/site-title` ↔ `artisanpack/site-title` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/site-title` direction is removed at the I7 cutover once
 * `core/site-title` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/site-title' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/site-title' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/site-title', attributes ),
        },
    ],
};

export default transforms;
