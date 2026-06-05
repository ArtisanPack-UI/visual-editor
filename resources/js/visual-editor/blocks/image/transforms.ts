/**
 * Image — transforms.
 *
 * Ported from `@wordpress/block-library/src/image/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/image` ↔
 * `artisanpack/image` so mixed documents round-trip losslessly during
 * the V2 rollout.
 */

import { createBlobURL, isBlobURL } from '@wordpress/blob';
import { createBlock, getBlockAttributes } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface ImageAttributes {
    readonly url?: string;
    readonly alt?: string;
    readonly caption?: string;
    readonly id?: number;
    readonly href?: string;
    readonly align?: string;
    readonly linkDestination?: string;
    readonly rel?: string;
    readonly linkClass?: string;
    readonly blob?: string;
    readonly anchor?: string;
    readonly [key: string]: unknown;
}

interface ShortcodeContext {
    readonly shortcode: { content: string };
}

interface ShortcodeArgs {
    readonly named: {
        readonly id?: string;
        readonly align?: string;
    };
}

export function stripFirstImage(
    _attributes: unknown,
    { shortcode }: ShortcodeContext
): string {
    const { body } = document.implementation.createHTMLDocument('');

    body.innerHTML = shortcode.content;

    let nodeToRemove: Element | ParentNode | null = body.querySelector('img');

    // If an image has parents, find the topmost node to remove.
    while (
        nodeToRemove &&
        nodeToRemove.parentNode &&
        nodeToRemove.parentNode !== body
    ) {
        nodeToRemove = nodeToRemove.parentNode;
    }

    if (nodeToRemove && nodeToRemove.parentNode) {
        nodeToRemove.parentNode.removeChild(nodeToRemove as ChildNode);
    }

    return body.innerHTML.trim();
}

function getFirstAnchorAttributeFromHTML(
    html: string,
    attributeName: string
): string | undefined {
    const { body } = document.implementation.createHTMLDocument('');

    body.innerHTML = html;

    const { firstElementChild } = body;

    if (firstElementChild && firstElementChild.nodeName === 'A') {
        return firstElementChild.getAttribute(attributeName) || undefined;
    }
    return undefined;
}

const imageSchema = {
    img: {
        attributes: ['src', 'alt', 'title'],
        classes: [
            'alignleft',
            'aligncenter',
            'alignright',
            'alignnone',
            /^wp-image-\d+$/,
        ],
    },
};

interface SchemaContext {
    readonly phrasingContentSchema: unknown;
}

const schema = ({ phrasingContentSchema }: SchemaContext) => ({
    figure: {
        require: ['img'],
        children: {
            ...imageSchema,
            a: {
                attributes: ['href', 'rel', 'target'],
                classes: ['*'],
                children: imageSchema,
            },
            figcaption: {
                children: phrasingContentSchema,
            },
        },
    },
});

const transforms = {
    from: [
        {
            type: 'raw',
            isMatch: (node: HTMLElement): boolean =>
                node.nodeName === 'FIGURE' && !!node.querySelector('img'),
            schema,
            transform: (node: HTMLElement) => {
                // Search both figure and image classes. Alignment could be
                // set on either. ID is set on the image.
                const img = node.querySelector('img');
                const className =
                    node.className + ' ' + (img?.className ?? '');
                const alignMatches =
                    /(?:^|\s)align(left|center|right)(?:$|\s)/.exec(className);
                const anchor = node.id === '' ? undefined : node.id;
                const align = alignMatches ? alignMatches[1] : undefined;
                const idMatches = /(?:^|\s)wp-image-(\d+)(?:$|\s)/.exec(
                    className
                );
                const id = idMatches ? Number(idMatches[1]) : undefined;
                const anchorElement = node.querySelector('a');
                const linkDestination =
                    anchorElement && anchorElement.href ? 'custom' : undefined;
                const href =
                    anchorElement && anchorElement.href
                        ? anchorElement.href
                        : undefined;
                const rel =
                    anchorElement && anchorElement.rel
                        ? anchorElement.rel
                        : undefined;
                const linkClass =
                    anchorElement && anchorElement.className
                        ? anchorElement.className
                        : undefined;
                const attributes = getBlockAttributes(
                    name,
                    node.outerHTML,
                    {
                        align,
                        id,
                        linkDestination,
                        href,
                        rel,
                        linkClass,
                        anchor,
                    }
                ) as ImageAttributes & { url?: string; blob?: string };

                if (isBlobURL(attributes.url)) {
                    (attributes as { blob?: string }).blob = attributes.url;
                    delete (attributes as { url?: string }).url;
                }

                return createBlock(name, attributes);
            },
        },
        {
            // Note: when dragging and dropping multiple files onto a gallery this overrides the
            // gallery transform in order to add new images to the gallery instead of
            // creating a new gallery.
            type: 'files',
            isMatch(files: readonly File[]): boolean {
                return files.every(
                    (file) => file.type.indexOf('image/') === 0
                );
            },
            transform(files: readonly File[]) {
                const blocks = files.map((file) => {
                    return createBlock(name, {
                        blob: createBlobURL(file),
                    });
                });
                return blocks;
            },
        },
        {
            type: 'shortcode',
            tag: 'caption',
            attributes: {
                url: {
                    type: 'string',
                    source: 'attribute',
                    attribute: 'src',
                    selector: 'img',
                },
                alt: {
                    type: 'string',
                    source: 'attribute',
                    attribute: 'alt',
                    selector: 'img',
                },
                caption: {
                    shortcode: stripFirstImage,
                },
                href: {
                    shortcode: (
                        _attributes: unknown,
                        { shortcode }: ShortcodeContext
                    ) => {
                        return getFirstAnchorAttributeFromHTML(
                            shortcode.content,
                            'href'
                        );
                    },
                },
                rel: {
                    shortcode: (
                        _attributes: unknown,
                        { shortcode }: ShortcodeContext
                    ) => {
                        return getFirstAnchorAttributeFromHTML(
                            shortcode.content,
                            'rel'
                        );
                    },
                },
                linkClass: {
                    shortcode: (
                        _attributes: unknown,
                        { shortcode }: ShortcodeContext
                    ) => {
                        return getFirstAnchorAttributeFromHTML(
                            shortcode.content,
                            'class'
                        );
                    },
                },
                id: {
                    type: 'number',
                    shortcode: ({ named: { id } }: ShortcodeArgs) => {
                        if (!id) {
                            return undefined;
                        }

                        return parseInt(id.replace('attachment_', ''), 10);
                    },
                },
                align: {
                    type: 'string',
                    shortcode: ({
                        named: { align = 'alignnone' },
                    }: ShortcodeArgs) => {
                        return align.replace('align', '');
                    },
                },
            },
        },
        {
            type: 'block',
            blocks: ['core/image'],
            transform: (attributes: ImageAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/image'],
            transform: (attributes: ImageAttributes) =>
                createBlock('core/image', attributes),
        },
    ],
};

export default transforms;
