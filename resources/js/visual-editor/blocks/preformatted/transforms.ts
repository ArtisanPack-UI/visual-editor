/**
 * Preformatted — transforms.
 *
 * Ported from `@wordpress/block-library/src/preformatted/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/preformatted` ↔ `artisanpack/preformatted`.
 */

import { createBlock } from '@wordpress/blocks';

import { CODE_LANGUAGE_CLASS } from '../code/languages';

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
    readonly phrasingContentSchema: Record<string, unknown>;
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
                    // Schemas for `pre` merge shallowly and this one lands
                    // last, so it has to repeat the Code block's class
                    // rule or the language is lost (#1045).
                    children: {
                        ...phrasingContentSchema,
                        code: {
                            ...(phrasingContentSchema.code as object),
                            classes: [CODE_LANGUAGE_CLASS],
                        },
                    },
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
        ...[
            'core/paragraph',
            'core/code',
            'core/verse',
            'artisanpack/paragraph',
            'artisanpack/code',
            'artisanpack/verse',
        ].map((blockName) => ({
            type: 'block' as const,
            blocks: [blockName],
            transform: (attributes: PreformattedAttributes) =>
                createBlock(blockName, attributes),
        })),
        {
            type: 'block',
            blocks: ['core/preformatted'],
            transform: (attributes: PreformattedAttributes) =>
                createBlock('core/preformatted', attributes),
        },
    ],
};

export default transforms;
