/**
 * Button — transforms.
 *
 * Upstream `@wordpress/block-library/src/button/` ships no transforms.js
 * (the parent buttons block owns those). The fork adds bidirectional
 * block transforms for `core/button` ↔ `artisanpack/button` so mixed
 * documents round-trip losslessly during the V2 rollout.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface ButtonAttributes {
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/button'],
            transform: (attributes: ButtonAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/button'],
            transform: (attributes: ButtonAttributes) =>
                createBlock('core/button', attributes),
        },
    ],
};

export default transforms;
