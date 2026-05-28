/**
 * Separator — transforms.
 *
 * Ported from `@wordpress/block-library/src/separator/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/separator` ↔ `artisanpack/separator` so mixed documents
 * round-trip losslessly during the V2 rollout.
 */

import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface SeparatorAttributes {
    readonly anchor?: string;
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'input',
            regExp: /^-{3,}$/,
            transform: () => [
                createBlock(name),
                createBlock(getDefaultBlockName() ?? 'core/paragraph'),
            ],
        },
        {
            type: 'raw',
            selector: 'hr',
            schema: {
                hr: {},
            },
        },
        {
            type: 'block',
            blocks: ['core/separator'],
            transform: (attributes: SeparatorAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/spacer'],
            transform: ({ anchor }: SeparatorAttributes) =>
                createBlock('core/spacer', {
                    anchor: anchor || undefined,
                }),
        },
        {
            type: 'block',
            blocks: ['core/separator'],
            transform: (attributes: SeparatorAttributes) =>
                createBlock('core/separator', attributes),
        },
    ],
};

export default transforms;
