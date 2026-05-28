/**
 * List — transforms.
 *
 * Ported from `@wordpress/block-library/src/list/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/list` ↔
 * `artisanpack/list`. The raw paste transform produces nested
 * `artisanpack/list-item` blocks.
 */

import { createBlock } from '@wordpress/blocks';
import { create, split, toHTMLString } from '@wordpress/rich-text';

import metadata from './block.json';

const { name } = metadata;

interface ListAttributes {
    content?: string;
    anchor?: string;
    [key: string]: unknown;
}

interface RawTransformContext {
    readonly phrasingContentSchema: unknown;
}

interface RawListElement {
    readonly tagName: string;
    readonly id?: string;
    readonly children: HTMLCollection;
    getAttribute(name: string): string | null;
    hasAttribute(name: string): boolean;
}

const LIST_STYLES: Record<string, string> = {
    A: 'upper-alpha',
    a: 'lower-alpha',
    I: 'upper-roman',
    i: 'lower-roman',
};

function getListContentSchema({
    phrasingContentSchema,
}: RawTransformContext): Record<string, unknown> {
    const listContentSchema: Record<string, unknown> = {
        ...(phrasingContentSchema as Record<string, unknown>),
        ul: {},
        ol: { attributes: ['type', 'start', 'reversed'] },
    };
    (['ul', 'ol'] as const).forEach((tag) => {
        (listContentSchema[tag] as Record<string, unknown>).children = {
            li: { children: listContentSchema },
        };
    });
    return listContentSchema;
}

function createListBlockFromDOMElement(
    listElement: RawListElement
): { name: string } {
    const type = listElement.getAttribute('type');
    const listAttributes = {
        ordered: 'OL' === listElement.tagName,
        anchor: listElement.id ? listElement.id : undefined,
        start: listElement.getAttribute('start')
            ? parseInt(listElement.getAttribute('start') as string, 10)
            : undefined,
        reversed: listElement.hasAttribute('reversed') ? true : undefined,
        type: type && LIST_STYLES[type] ? LIST_STYLES[type] : undefined,
    };

    const innerBlocks = Array.from(listElement.children).map((listItem) => {
        const itemEl = listItem as Element;
        const children = Array.from(itemEl.childNodes).filter(
            (node) =>
                node.nodeType !== Node.TEXT_NODE ||
                (node.textContent ?? '').trim().length !== 0
        );
        children.reverse();
        const [nestedList, ...nodes] = children as Element[];

        const hasNestedList =
            (nestedList as Element)?.tagName === 'UL' ||
            (nestedList as Element)?.tagName === 'OL';

        if (!hasNestedList) {
            return createBlock('artisanpack/list-item', {
                content: itemEl.innerHTML,
            });
        }

        const htmlNodes = nodes.map((node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                return node.textContent ?? '';
            }
            return (node as Element).outerHTML;
        });
        htmlNodes.reverse();
        const childAttributes = { content: htmlNodes.join('').trim() };
        const childInnerBlocks = [
            createListBlockFromDOMElement(
                nestedList as unknown as RawListElement
            ),
        ];
        return createBlock(
            'artisanpack/list-item',
            childAttributes,
            childInnerBlocks as unknown[]
        );
    });

    return createBlock(name, listAttributes, innerBlocks as unknown[]);
}

function getListContentFlat(
    blocks: Array<{
        name: string;
        attributes: Record<string, unknown>;
        innerBlocks?: unknown[];
    }>
): string[] {
    return blocks.flatMap(({ name: blockName, attributes, innerBlocks = [] }) => {
        if (blockName === 'artisanpack/list-item' || blockName === 'core/list-item') {
            return [
                String(attributes.content ?? ''),
                ...getListContentFlat(
                    innerBlocks as Array<{
                        name: string;
                        attributes: Record<string, unknown>;
                        innerBlocks?: unknown[];
                    }>
                ),
            ];
        }
        return getListContentFlat(
            innerBlocks as Array<{
                name: string;
                attributes: Record<string, unknown>;
                innerBlocks?: unknown[];
            }>
        );
    });
}

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: [
                'core/paragraph',
                'core/heading',
                'artisanpack/paragraph',
                'artisanpack/heading',
            ],
            transform: (blockAttributes: ListAttributes[]) => {
                let childBlocks: unknown[] = [];
                if (blockAttributes.length > 1) {
                    childBlocks = blockAttributes.map(({ content }) =>
                        createBlock('artisanpack/list-item', { content })
                    );
                } else if (blockAttributes.length === 1) {
                    const value = create({ html: blockAttributes[0].content });
                    childBlocks = split(value, '\n').map((result) =>
                        createBlock('artisanpack/list-item', {
                            content: toHTMLString({ value: result }),
                        })
                    );
                }
                return createBlock(
                    name,
                    { anchor: blockAttributes[0]?.anchor },
                    childBlocks
                );
            },
        },
        {
            type: 'raw',
            selector: 'ol,ul',
            schema: (args: RawTransformContext) => ({
                ol: (getListContentSchema(args) as { ol: unknown }).ol,
                ul: (getListContentSchema(args) as { ul: unknown }).ul,
            }),
            transform: createListBlockFromDOMElement,
        },
        ...['*', '-'].map((prefix) => ({
            type: 'prefix' as const,
            prefix,
            transform(content: string) {
                return createBlock(name, {}, [
                    createBlock('artisanpack/list-item', { content }),
                ]);
            },
        })),
        ...['1.', '1)'].map((prefix) => ({
            type: 'prefix' as const,
            prefix,
            transform(content: string) {
                return createBlock(name, { ordered: true }, [
                    createBlock('artisanpack/list-item', { content }),
                ]);
            },
        })),
        {
            type: 'block',
            blocks: ['core/list'],
            transform: (
                attributes: ListAttributes,
                innerBlocks: Array<{
                    name: string;
                    attributes: Record<string, unknown>;
                    innerBlocks: unknown[];
                }>
            ) =>
                createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        ...['core/paragraph', 'core/heading'].map((blockName) => ({
            type: 'block' as const,
            blocks: [blockName],
            transform: (
                _attributes: ListAttributes,
                childBlocks: Array<{
                    name: string;
                    attributes: Record<string, unknown>;
                    innerBlocks?: unknown[];
                }>
            ) => {
                return getListContentFlat(childBlocks).map((content) =>
                    createBlock(blockName, { content })
                );
            },
        })),
        {
            type: 'block',
            blocks: ['core/list'],
            transform: (
                attributes: ListAttributes,
                innerBlocks: Array<unknown>
            ) => createBlock('core/list', attributes, innerBlocks),
        },
    ],
};

export default transforms;
