/**
 * SiteLogo — transforms.
 *
 * Bidirectional `core/site-logo` ↔ `artisanpack/site-logo` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/site-logo` direction is removed at the I7 cutover once
 * `core/site-logo` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/site-logo' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/site-logo' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/site-logo', attributes ),
        },
    ],
};

export default transforms;
