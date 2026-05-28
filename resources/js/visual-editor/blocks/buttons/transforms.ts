/**
 * Buttons — transforms.
 *
 * Ported from `@wordpress/block-library/src/buttons/transforms.js`
 * (v9.43.0). Multi-block button + paragraph transforms preserved (now
 * producing artisanpack/button children). Extended with bidirectional
 * block transforms for core/buttons ↔ artisanpack/buttons so mixed
 * documents round-trip losslessly during the V2 rollout.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;
const BUTTON_BLOCK = 'artisanpack/button';

interface InnerBlock {
    name?: string;
    attributes?: Record<string, unknown>;
    innerBlocks?: InnerBlock[];
    [key: string]: unknown;
}

/**
 * Recursively rename `from` → `to` across the inner-block tree so
 * `core/buttons` ↔ `artisanpack/buttons` round-trips carry the child
 * `button` blocks into the matching namespace.
 */
function remapBlockNames(
    blocks: readonly InnerBlock[] | undefined,
    from: string,
    to: string
): InnerBlock[] {
    if (!Array.isArray(blocks)) {
        return [];
    }
    return blocks.map((block) => ({
        ...block,
        name: block.name === from ? to : block.name,
        innerBlocks: remapBlockNames(block.innerBlocks, from, to),
    }));
}

interface ButtonAttributes {
    readonly content?: string;
    readonly [key: string]: unknown;
}

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['artisanpack/button', 'core/button'],
            transform: (buttons: ButtonAttributes[]) =>
                createBlock(
                    name,
                    {},
                    buttons.map((attributes) =>
                        createBlock('artisanpack/button', attributes)
                    )
                ),
        },
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['core/paragraph'],
            transform: (paragraphs: ButtonAttributes[]) =>
                createBlock(
                    name,
                    {},
                    paragraphs.map((attributes) => {
                        const { content } = attributes;
                        const text =
                            typeof content === 'string'
                                ? content.replace(/<[^>]*>/g, '')
                                : '';
                        const linkMatch =
                            typeof content === 'string'
                                ? content.match(/href="([^"]+)"/)
                                : null;
                        const url = linkMatch?.[1];
                        return createBlock('artisanpack/button', {
                            ...attributes,
                            text,
                            url,
                        });
                    })
                ),
            isMatch: (paragraphs: ButtonAttributes[]): boolean => {
                return paragraphs.every((attributes) => {
                    const content =
                        typeof attributes.content === 'string'
                            ? attributes.content
                            : '';
                    const text = content.replace(/<[^>]*>/g, '');
                    const linkCount = (content.match(/<a /g) ?? []).length;
                    return text.length <= 30 && linkCount <= 1;
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/buttons'],
            transform: (
                attributes: Record<string, unknown>,
                innerBlocks: unknown[]
            ) =>
                createBlock(
                    name,
                    attributes,
                    remapBlockNames(
                        innerBlocks as InnerBlock[],
                        'core/button',
                        BUTTON_BLOCK
                    )
                ),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/buttons'],
            transform: (
                attributes: Record<string, unknown>,
                innerBlocks: unknown[]
            ) =>
                createBlock(
                    'core/buttons',
                    attributes,
                    remapBlockNames(
                        innerBlocks as InnerBlock[],
                        BUTTON_BLOCK,
                        'core/button'
                    )
                ),
        },
    ],
};

export default transforms;
