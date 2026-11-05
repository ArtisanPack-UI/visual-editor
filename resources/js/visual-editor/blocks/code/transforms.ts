/**
 * Code — transforms.
 *
 * Ported from `@wordpress/block-library/src/code/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/code` ↔
 * `artisanpack/code`.
 */

import { createBlock, getBlockAttributes } from '@wordpress/blocks';
import { create, toHTMLString } from '@wordpress/rich-text';

import metadata from './block.json';
import { CODE_LANGUAGE_CLASS, normalizeCodeLanguage } from './languages';

const { name } = metadata;

interface CodeAttributes {
    content?: string;
    [key: string]: unknown;
}

interface RawTransformNode {
    readonly nodeName: string;
    readonly children: { readonly length: number };
    readonly firstChild: { readonly nodeName: string; readonly classList?: Iterable<string> };
    readonly outerHTML: string;
}

const transforms = {
    from: [
        {
            type: 'input',
            regExp: /^```$/,
            transform: () => createBlock(name),
        },
        {
            type: 'block',
            blocks: ['core/paragraph', 'artisanpack/paragraph'],
            transform: (attributes: CodeAttributes) => {
                const { content } = attributes;
                return createBlock(name, { ...attributes, content });
            },
        },
        {
            type: 'block',
            blocks: ['core/html'],
            transform: (attributes: CodeAttributes) => {
                const { content: text } = attributes;
                return createBlock(name, {
                    ...attributes,
                    content: toHTMLString({ value: create({ text }) }),
                });
            },
        },
        {
            type: 'raw',
            isMatch: (node: RawTransformNode) =>
                node.nodeName === 'PRE' &&
                node.children.length === 1 &&
                node.firstChild.nodeName === 'CODE',
            schema: {
                pre: {
                    children: {
                        code: {
                            classes: [CODE_LANGUAGE_CLASS],
                            children: {
                                '#text': {},
                            },
                        },
                    },
                },
            },
            transform: ({ firstChild, outerHTML }: RawTransformNode) => {
                const languageClass = Array.from(firstChild.classList ?? []).find((className) =>
                    CODE_LANGUAGE_CLASS.test(className)
                );

                return createBlock(name, {
                    ...getBlockAttributes(name, outerHTML),
                    language: normalizeCodeLanguage(
                        languageClass?.replace(CODE_LANGUAGE_CLASS, '') ?? ''
                    ),
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/code'],
            transform: (attributes: CodeAttributes) => createBlock(name, attributes),
        },
    ],
    to: [
        ...['core/paragraph', 'artisanpack/paragraph'].map((blockName) => ({
            type: 'block' as const,
            blocks: [blockName],
            transform: (attributes: CodeAttributes) => {
                const { content } = attributes;
                return createBlock(blockName, { ...attributes, content });
            },
        })),
        {
            type: 'block',
            blocks: ['core/code'],
            transform: (attributes: CodeAttributes) =>
                createBlock('core/code', attributes),
        },
    ],
};

export default transforms;
