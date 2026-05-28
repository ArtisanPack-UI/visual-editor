/**
 * Spacer — transforms.
 *
 * Ported from `@wordpress/block-library/src/spacer/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/spacer` ↔ `artisanpack/spacer` so mixed documents round-trip
 * losslessly during the V2 rollout.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface SpacerAttributes {
    readonly anchor?: string;
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/spacer'],
            transform: (attributes: SpacerAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/separator'],
            transform: ({ anchor }: SpacerAttributes) =>
                createBlock('core/separator', {
                    anchor: anchor || undefined,
                }),
        },
        {
            type: 'block',
            blocks: ['core/spacer'],
            transform: (attributes: SpacerAttributes) =>
                createBlock('core/spacer', attributes),
        },
    ],
};

export default transforms;
