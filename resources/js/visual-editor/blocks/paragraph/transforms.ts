/**
 * Paragraph — transforms.
 *
 * `from` raw-HTML transform mirrors `@wordpress/block-library/src/paragraph/transforms.js`
 * (v9.43.0). Two extra block-to-block transforms make the fork round-trip cleanly
 * with upstream `core/paragraph` so existing host documents containing
 * `core/paragraph` blocks are convertible to `artisanpack/paragraph` (and back)
 * without losing attributes or rich-text content.
 */

import { createBlock, getBlockAttributes } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface ParagraphAttributes {
    content?: string;
    direction?: 'ltr' | 'rtl';
    dropCap?: boolean;
    placeholder?: string;
    align?: string;
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
    readonly style?: { readonly textAlign?: string };
}

interface CoreParagraphBlock {
    readonly name: 'core/paragraph';
    readonly attributes: ParagraphAttributes;
    readonly innerBlocks: readonly unknown[];
}

interface ArtisanpackParagraphBlock {
    readonly name: 'artisanpack/paragraph';
    readonly attributes: ParagraphAttributes;
    readonly innerBlocks: readonly unknown[];
}

const transforms = {
    from: [
        {
            type: 'raw',
            // Paragraph is the fallback raw transform; match last.
            priority: 20,
            selector: 'p',
            schema: ({ phrasingContentSchema, isPaste }: RawTransformContext) => ({
                p: {
                    children: phrasingContentSchema,
                    attributes: isPaste ? [] : ['style', 'id'],
                },
            }),
            transform(node: DOMNodeLike) {
                const attributes = getBlockAttributes(name, node.outerHTML) as ParagraphAttributes;
                const { textAlign } = node.style || {};

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
        {
            type: 'block',
            blocks: ['core/paragraph'],
            transform: (attributes: ParagraphAttributes) => createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/paragraph'],
            transform: (attributes: ParagraphAttributes) =>
                createBlock('core/paragraph', attributes),
        },
    ],
};

export type { CoreParagraphBlock, ArtisanpackParagraphBlock };
export default transforms;
