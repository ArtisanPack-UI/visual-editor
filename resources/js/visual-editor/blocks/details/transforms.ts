/**
 * Details — transforms.
 *
 * Ported from `@wordpress/block-library/src/details/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/details` ↔ `artisanpack/details` so mixed documents round-trip
 * losslessly during the V2 rollout.
 */

import { createBlock, cloneBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface BlockShape {
    readonly name: string;
    readonly attributes?: Record<string, unknown>;
    readonly innerBlocks?: BlockShape[];
}

interface DetailsAttributes {
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['*'],
            isMatch(_attrs: Record<string, unknown>, blocks: BlockShape[]) {
                return !(
                    blocks.length === 1 &&
                    (blocks[0].name === name || blocks[0].name === 'core/details')
                );
            },
            __experimentalConvert(blocks: BlockShape[]) {
                return createBlock(
                    name,
                    {},
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    blocks.map((block) => (cloneBlock as any)(block))
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/details'],
            transform: (
                attributes: DetailsAttributes,
                innerBlocks: BlockShape[]
            ) =>
                createBlock(
                    name,
                    attributes,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    (innerBlocks || []).map((block) => (cloneBlock as any)(block))
                ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/details'],
            transform: (
                attributes: DetailsAttributes,
                innerBlocks: BlockShape[]
            ) =>
                createBlock(
                    'core/details',
                    attributes,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    (innerBlocks || []).map((block) => (cloneBlock as any)(block))
                ),
        },
    ],
};

export default transforms;
