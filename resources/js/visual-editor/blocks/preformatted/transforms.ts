/**
 * Preformatted — transforms.
 *
 * Ported from `@wordpress/block-library/src/preformatted/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/preformatted` ↔ `artisanpack/preformatted`.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface PreformattedAttributes {
    content?: string;
    anchor?: string;
    [key: string]: unknown;
}

interface RawTransformNode {
    readonly nodeName: string;
    readonly children: { readonly length: number };
    readonly firstChild: { readonly nodeName: string };
}

interface RawTransformContext {
    readonly phrasingContentSchema: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: [
                'core/code',
                'core/paragraph',
                'core/verse',
                'artisanpack/code',
                'artisanpack/paragraph',
                'artisanpack/verse',
            ],
            transform: ({ content, anchor }: PreformattedAttributes) =>
                createBlock(name, { content, anchor }),
        },
        {
            type: 'raw',
            isMatch: (node: RawTransformNode) =>
                node.nodeName === 'PRE' &&
                !(
                    node.children.length === 1 &&
                    node.firstChild.nodeName === 'CODE'
                ),
            schema: ({ phrasingContentSchema }: RawTransformContext) => ({
                pre: {
                    children: phrasingContentSchema,
                },
            }),
        },
        {
            type: 'block',
            blocks: ['core/preformatted'],
            transform: (attributes: PreformattedAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/paragraph'],
            transform: (attributes: PreformattedAttributes) =>
                createBlock('core/paragraph', attributes),
        },
        {
            type: 'block',
            blocks: ['core/code'],
            transform: (attributes: PreformattedAttributes) =>
                createBlock('core/code', attributes),
        },
        {
            type: 'block',
            blocks: ['core/verse'],
            transform: (attributes: PreformattedAttributes) =>
                createBlock('core/verse', attributes),
        },
        {
            type: 'block',
            blocks: ['core/preformatted'],
            transform: (attributes: PreformattedAttributes) =>
                createBlock('core/preformatted', attributes),
        },
    ],
};

export default transforms;
