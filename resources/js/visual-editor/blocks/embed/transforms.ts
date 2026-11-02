/**
 * Embed — transforms.
 *
 * Ported from `@wordpress/block-library/src/embed/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/embed` ↔ `artisanpack/embed` so mixed documents round-trip
 * losslessly during the V2 rollout.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';
import { removeAspectRatioClasses, renderUrlLink } from './util';

const { name: EMBED_BLOCK } = metadata;

interface EmbedAttributes {
    readonly url?: string;
    readonly caption?: string;
    readonly className?: string;
    readonly [key: string]: unknown;
}

interface RawNode {
    readonly nodeName?: string;
    readonly textContent?: string | null;
}

const transforms = {
    from: [
        {
            type: 'raw',
            isMatch: (node: RawNode): boolean =>
                node.nodeName === 'P' &&
                /^\s*(https?:\/\/\S+)\s*$/i.test(node.textContent ?? '') &&
                (node.textContent?.match(/https/gi)?.length ?? 0) === 1,
            transform: (node: RawNode) => {
                return createBlock(EMBED_BLOCK, {
                    url: (node.textContent ?? '').trim(),
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/embed'],
            transform: (attributes: EmbedAttributes) =>
                createBlock(EMBED_BLOCK, attributes),
        },
    ],
    to: [
        ...['core/paragraph', 'artisanpack/paragraph'].map((blockName) => ({
            type: 'block' as const,
            blocks: [blockName],
            isMatch: ({ url }: EmbedAttributes): boolean =>
                /^https?:\/\//i.test(url ?? ''),
            transform: ({ url, caption, className }: EmbedAttributes) => {
                let value = renderUrlLink(url as string);
                if (caption?.trim()) {
                    value += `<br />${caption}`;
                }
                return createBlock(blockName, {
                    content: value,
                    className: removeAspectRatioClasses(className),
                });
            },
        })),
        {
            type: 'block',
            blocks: ['core/embed'],
            transform: (attributes: EmbedAttributes) =>
                createBlock('core/embed', attributes),
        },
    ],
};

export default transforms;
