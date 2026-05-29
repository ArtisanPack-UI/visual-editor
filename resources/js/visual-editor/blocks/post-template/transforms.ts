/**
 * Post Template — transforms.
 *
 * `@wordpress/block-library/src/post-template` ships no `transforms.js`.
 * The fork adds bidirectional block transforms for
 * `core/post-template` ↔ `artisanpack/post-template` so mixed documents
 * round-trip losslessly during the V1 rollout. The `to: core/post-template`
 * direction is removed at the I7 cutover once `core/post-template` is no
 * longer registered. Phase I6 loop / feed cluster (#414).
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface PostTemplateAttributes {
    readonly [ key: string ]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [ 'core/post-template' ],
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            transform: ( attributes: PostTemplateAttributes, innerBlocks: any[] ) =>
                createBlock( name, attributes, innerBlocks ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: [ 'core/post-template' ],
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            transform: ( attributes: PostTemplateAttributes, innerBlocks: any[] ) =>
                createBlock( 'core/post-template', attributes, innerBlocks ),
        },
    ],
};

export default transforms;
