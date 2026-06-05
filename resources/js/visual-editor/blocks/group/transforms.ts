/**
 * Group — transforms.
 *
 * Ported from `@wordpress/block-library/src/group/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/group` ↔ `artisanpack/group` so mixed documents round-trip
 * losslessly during the V2 rollout.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface BlockShape {
    name: string;
    attributes: { align?: string; [key: string]: unknown };
    innerBlocks: BlockShape[];
}

interface GroupAttributes {
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['*'],
            __experimentalConvert(blocks: BlockShape[]) {
                const alignments = ['wide', 'full'];

                const widestAlignment = blocks.reduce<string | undefined>(
                    (accumulator, block) => {
                        const { align } = block.attributes;
                        return alignments.indexOf(align ?? '') >
                            alignments.indexOf(accumulator ?? '')
                            ? align
                            : accumulator;
                    },
                    undefined
                );

                const groupInnerBlocks = blocks.map((block) =>
                    createBlock(
                        block.name,
                        block.attributes,
                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                        block.innerBlocks as any
                    )
                );

                return createBlock(
                    name,
                    {
                        align: widestAlignment,
                        layout: { type: 'constrained' },
                    },
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    groupInnerBlocks as any
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/group'],
            transform: (
                attributes: GroupAttributes,
                innerBlocks: BlockShape[]
            ) =>
                createBlock(
                    name,
                    attributes,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    innerBlocks as any
                ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/group'],
            transform: (
                attributes: GroupAttributes,
                innerBlocks: BlockShape[]
            ) =>
                createBlock(
                    'core/group',
                    attributes,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    innerBlocks as any
                ),
        },
    ],
};

export default transforms;
