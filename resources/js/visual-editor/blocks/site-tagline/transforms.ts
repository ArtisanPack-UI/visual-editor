/**
 * SiteTagline — transforms.
 *
 * Bidirectional `core/site-tagline` ↔ `artisanpack/site-tagline` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/site-tagline` direction is removed at the I7 cutover once
 * `core/site-tagline` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/site-tagline' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/site-tagline' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/site-tagline', attributes ),
        },
    ],
};

export default transforms;
