/**
 * PostFeaturedImage — transforms.
 *
 * Bidirectional `core/post-featured-image` ↔ `artisanpack/post-featured-image` block transforms so
 * mixed documents round-trip losslessly during the V1 rollout. The
 * `to: core/post-featured-image` direction is removed at the I7 cutover once
 * `core/post-featured-image` is no longer registered. Phase I5 entity cluster (#413).
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
            blocks: [ 'core/post-featured-image' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-featured-image' ],
            transform: ( attributes: EntityAttributes ) =>
                createBlock( 'core/post-featured-image', attributes ),
        },
    ],
};

export default transforms;
