/**
 * Heading — transforms.
 *
 * Ported from `@wordpress/block-library/src/heading/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/heading` ↔ `artisanpack/heading` so mixed documents round-trip
 * losslessly during the V2 rollout. The upstream `core/paragraph`
 * transforms are namespaced to `artisanpack/paragraph` since this fork
 * lives in the same package and the renderers map both blocks to the
 * same component.
 */

import { createBlock, getBlockAttributes } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface HeadingAttributes {
    content?: string;
    level?: number;
    anchor?: string;
    placeholder?: string;
    style?: {
        typography?: {
            textAlign?: string;
        } & Record<string, unknown>;
    };
    [key: string]: unknown;
}

interface RawTransformContext {
    readonly phrasingContentSchema: unknown;
    readonly isPaste: boolean;
}

interface DOMNodeLike {
    readonly outerHTML: string;
    readonly nodeName: string;
    readonly style?: { readonly textAlign?: string };
}

function getLevelFromHeadingNodeName(nodeName: string): number {
    return Number(nodeName.substring(1));
}

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['core/paragraph', 'artisanpack/paragraph'],
            transform: (attributes: HeadingAttributes[]) =>
                attributes.map((_attributes) => {
                    const { content, anchor, style } = _attributes;
                    const textAlign = style?.typography?.textAlign;
                    return createBlock(name, {
                        content,
                        anchor,
                        ...(textAlign && {
                            style: {
                                typography: {
                                    textAlign,
                                },
                            },
                        }),
                    });
                }),
        },
        {
            type: 'raw',
            selector: 'h1,h2,h3,h4,h5,h6',
            schema: ({ phrasingContentSchema, isPaste }: RawTransformContext) => {
                const schema = {
                    children: phrasingContentSchema,
                    attributes: isPaste ? [] : ['style', 'id'],
                };
                return {
                    h1: schema,
                    h2: schema,
                    h3: schema,
                    h4: schema,
                    h5: schema,
                    h6: schema,
                };
            },
            transform(node: DOMNodeLike) {
                const attributes = getBlockAttributes(
                    name,
                    node.outerHTML
                ) as HeadingAttributes;
                const { textAlign } = node.style || {};

                attributes.level = getLevelFromHeadingNodeName(node.nodeName);

                if (
                    textAlign === 'left' ||
                    textAlign === 'center' ||
                    textAlign === 'right'
                ) {
                    attributes.style = {
                        ...attributes.style,
                        typography: {
                            ...attributes.style?.typography,
                            textAlign,
                        },
                    };
                }

                return createBlock(name, attributes);
            },
        },
        ...[1, 2, 3, 4, 5, 6].map((level) => ({
            type: 'prefix',
            prefix: Array(level + 1).join('#'),
            transform(content: string) {
                return createBlock(name, { level, content });
            },
        })),
        ...[1, 2, 3, 4, 5, 6].map((level) => ({
            type: 'enter',
            regExp: new RegExp(`^/(h|H)${level}$`),
            transform: () => createBlock(name, { level }),
        })),
        {
            type: 'block',
            blocks: ['core/heading'],
            transform: (attributes: HeadingAttributes) => createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['core/paragraph'],
            transform: (attributes: HeadingAttributes[]) =>
                attributes.map((_attributes) => {
                    const { content, style } = _attributes;
                    const textAlign = style?.typography?.textAlign;
                    return createBlock('core/paragraph', {
                        content,
                        ...(textAlign && {
                            style: {
                                typography: {
                                    textAlign,
                                },
                            },
                        }),
                    });
                }),
        },
        {
            type: 'block',
            blocks: ['core/heading'],
            transform: (attributes: HeadingAttributes) =>
                createBlock('core/heading', attributes),
        },
    ],
};

export default transforms;
