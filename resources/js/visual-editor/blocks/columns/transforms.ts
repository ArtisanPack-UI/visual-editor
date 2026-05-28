/**
 * Columns — transforms.
 *
 * Ported from `@wordpress/block-library/src/columns/transforms.js`
 * (v9.43.0). Inner-block templates target `artisanpack/column`.
 * Extended with bidirectional `core/columns` ↔ `artisanpack/columns`
 * block transforms so mixed documents round-trip during the V2 rollout.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import {
    createBlock,
    createBlocksFromInnerBlocksTemplate,
} from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;
const COLUMN_BLOCK = 'artisanpack/column';
const MAXIMUM_SELECTED_BLOCKS = 6;

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['*'],
            __experimentalConvert: (blocks: any[]) => {
                const columnWidth = +(100 / blocks.length).toFixed(2);
                const innerBlocksTemplate = blocks.map(
                    ({ name: blockName, attributes, innerBlocks }) => [
                        COLUMN_BLOCK,
                        { width: `${columnWidth}%` },
                        [[blockName, { ...attributes }, innerBlocks]],
                    ]
                );
                return createBlock(
                    name,
                    {},
                    createBlocksFromInnerBlocksTemplate(
                        innerBlocksTemplate as any
                    )
                );
            },
            isMatch: (
                { length: selectedBlocksLength }: { length: number },
                blocks: any[]
            ) => {
                if (blocks.length === 1 && blocks[0].name === name) {
                    return false;
                }
                return (
                    selectedBlocksLength > 0 &&
                    selectedBlocksLength <= MAXIMUM_SELECTED_BLOCKS
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/media-text'],
            priority: 1,
            transform: (attributes: any, innerBlocks: any[]) => {
                const {
                    align,
                    backgroundColor,
                    textColor,
                    style,
                    mediaAlt: alt,
                    mediaId: id,
                    mediaPosition,
                    mediaSizeSlug: sizeSlug,
                    mediaType,
                    mediaUrl: url,
                    mediaWidth,
                    verticalAlignment,
                } = attributes;
                let media;
                if (mediaType === 'image' || !mediaType) {
                    const imageAttrs = { id, alt, url, sizeSlug };
                    const linkAttrs = {
                        href: attributes.href,
                        linkClass: attributes.linkClass,
                        linkDestination: attributes.linkDestination,
                        linkTarget: attributes.linkTarget,
                        rel: attributes.rel,
                    };
                    media = ['core/image', { ...imageAttrs, ...linkAttrs }];
                } else {
                    media = ['core/video', { id, src: url }];
                }
                const innerBlocksTemplate: any[] = [
                    [COLUMN_BLOCK, { width: `${mediaWidth}%` }, [media]],
                    [
                        COLUMN_BLOCK,
                        { width: `${100 - mediaWidth}%` },
                        innerBlocks,
                    ],
                ];
                if (mediaPosition === 'right') {
                    innerBlocksTemplate.reverse();
                }
                return createBlock(
                    name,
                    {
                        align,
                        backgroundColor,
                        textColor,
                        style,
                        verticalAlignment,
                    },
                    createBlocksFromInnerBlocksTemplate(innerBlocksTemplate)
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/columns'],
            transform: (attributes: any, innerBlocks: any[]) =>
                createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/columns'],
            transform: (attributes: any, innerBlocks: any[]) =>
                createBlock('core/columns', attributes, innerBlocks),
        },
    ],
    ungroup: (_attributes: any, innerBlocks: any[]): any[] =>
        innerBlocks.flatMap((innerBlock) => innerBlock.innerBlocks),
};

export default transforms;
