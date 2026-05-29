/**
 * Latest Posts — transforms.
 *
 * `@wordpress/block-library/src/latest-posts` (v9.43.0) ships no
 * `transforms.js`. The fork adds bidirectional block transforms for
 * `core/latest-posts` ↔ `artisanpack/latest-posts` so mixed documents
 * round-trip losslessly during the V1 rollout. The `to: core/latest-posts`
 * direction is removed at the I7 cutover once `core/latest-posts` is no
 * longer registered.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface LatestPostsAttributes {
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/latest-posts' ],
            transform: ( attributes: LatestPostsAttributes ) =>
                createBlock( name, attributes ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/latest-posts' ],
            transform: ( attributes: LatestPostsAttributes ) =>
                createBlock( 'core/latest-posts', attributes ),
        },
    ],
};

export default transforms;
