/**
 * Pullquote — transforms.
 *
 * Ported from `@wordpress/block-library/src/pullquote/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/pullquote` ↔ `artisanpack/pullquote`.
 */

import { createBlock } from '@wordpress/blocks';
import { create, join, toHTMLString } from '@wordpress/rich-text';

import metadata from './block.json';

const { name } = metadata;

interface PullquoteAttributes {
    value?: string;
    citation?: string;
    content?: string;
    anchor?: string;
    [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['core/paragraph', 'artisanpack/paragraph'],
            transform: (attributes: PullquoteAttributes[]) =>
                createBlock(name, {
                    value: toHTMLString({
                        value: join(
                            attributes.map(({ content }) =>
                                create({ html: content ?? '' })
                            ),
                            '\n'
                        ),
                    }),
                    anchor: attributes[0]?.anchor,
                }),
        },
        {
            type: 'block',
            blocks: ['core/heading', 'artisanpack/heading'],
            transform: ({ content, anchor }: PullquoteAttributes) =>
                createBlock(name, { value: content, anchor }),
        },
        {
            type: 'block',
            blocks: ['core/pullquote'],
            transform: (attributes: PullquoteAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/paragraph'],
            transform: ({ value, citation }: PullquoteAttributes) => {
                const paragraphs = [];
                if (value) {
                    paragraphs.push(createBlock('core/paragraph', { content: value }));
                }
                if (citation) {
                    paragraphs.push(createBlock('core/paragraph', { content: citation }));
                }
                if (paragraphs.length === 0) {
                    return createBlock('core/paragraph', { content: '' });
                }
                return paragraphs;
            },
        },
        {
            type: 'block',
            blocks: ['core/heading'],
            transform: ({ value, citation }: PullquoteAttributes) => {
                if (!value) {
                    return createBlock('core/heading', { content: citation });
                }
                const headingBlock = createBlock('core/heading', { content: value });
                if (!citation) {
                    return headingBlock;
                }
                return [
                    headingBlock,
                    createBlock('core/heading', { content: citation }),
                ];
            },
        },
        {
            type: 'block',
            blocks: ['core/pullquote'],
            transform: (attributes: PullquoteAttributes) =>
                createBlock('core/pullquote', attributes),
        },
    ],
};

export default transforms;
