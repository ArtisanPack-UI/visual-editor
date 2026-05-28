/**
 * Quote — transforms.
 *
 * Ported from `@wordpress/block-library/src/quote/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/quote` ↔
 * `artisanpack/quote`.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface QuoteAttributes {
    value?: string;
    citation?: string;
    align?: string;
    textAlign?: string;
    anchor?: string;
    fontSize?: string;
    style?: Record<string, unknown> | number;
    [key: string]: unknown;
}

interface BlockLike {
    readonly name: string;
    readonly attributes: Record<string, unknown>;
    readonly innerBlocks: readonly BlockLike[];
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/verse', 'artisanpack/verse'],
            transform: ({ content }: { content: string }) =>
                createBlock(name, {}, [
                    createBlock('core/paragraph', { content }),
                ]),
        },
        {
            type: 'block',
            blocks: ['core/pullquote', 'artisanpack/pullquote'],
            transform: ({
                value,
                align,
                citation,
                anchor,
                fontSize,
                style,
            }: QuoteAttributes) =>
                createBlock(
                    name,
                    { align, citation, anchor, fontSize, style },
                    [createBlock('core/paragraph', { content: value })]
                ),
        },
        {
            type: 'prefix',
            prefix: '>',
            transform: (content: string) =>
                createBlock(name, {}, [
                    createBlock('core/paragraph', { content }),
                ]),
        },
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['*'],
            isMatch: (_attrs: unknown, blocks: BlockLike[]) => {
                if (blocks.length === 1) {
                    return [
                        'core/paragraph',
                        'core/heading',
                        'core/list',
                        'core/pullquote',
                        'artisanpack/paragraph',
                        'artisanpack/heading',
                        'artisanpack/list',
                        'artisanpack/pullquote',
                    ].includes(blocks[0].name);
                }
                return !blocks.some(
                    ({ name: blockName }) =>
                        blockName === 'core/quote' || blockName === name
                );
            },
            __experimentalConvert: (blocks: BlockLike[]) =>
                createBlock(
                    name,
                    {},
                    blocks.map((block) =>
                        createBlock(
                            block.name,
                            block.attributes,
                            block.innerBlocks as unknown as BlockLike[]
                        )
                    )
                ),
        },
        {
            type: 'block',
            blocks: ['core/quote'],
            transform: (attributes: QuoteAttributes, innerBlocks: BlockLike[]) =>
                createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/quote'],
            transform: (attributes: QuoteAttributes, innerBlocks: BlockLike[]) =>
                createBlock('core/quote', attributes, innerBlocks),
        },
    ],
};

export default transforms;
