/**
 * Media & Text — transforms.
 *
 * Ported from `@wordpress/block-library/src/media-text/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/media-text` ↔ `artisanpack/media-text` so mixed documents
 * round-trip losslessly during the V2 rollout.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface MediaTextAttributes {
    readonly [key: string]: unknown;
}

interface ImageBlockAttributes {
    readonly alt?: string;
    readonly url?: string;
    readonly id?: number;
    readonly anchor?: string;
}

interface VideoBlockAttributes {
    readonly src?: string;
    readonly id?: number;
    readonly anchor?: string;
}

interface CoverBlockAttributes {
    readonly align?: string;
    readonly alt?: string;
    readonly anchor?: string;
    readonly backgroundType?: string;
    readonly customGradient?: string;
    readonly customOverlayColor?: string;
    readonly gradient?: string;
    readonly id?: number;
    readonly overlayColor?: string;
    readonly style?: {
        color?: {
            text?: string;
            background?: string;
            gradient?: string;
        };
    };
    readonly textColor?: string;
    readonly url?: string;
    readonly useFeaturedImage?: boolean;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/image'],
            transform: ({ alt, url, id, anchor }: ImageBlockAttributes) =>
                createBlock(name, {
                    mediaAlt: alt,
                    mediaId: id,
                    mediaUrl: url,
                    mediaType: 'image',
                    anchor,
                }),
        },
        {
            type: 'block',
            blocks: ['core/video'],
            transform: ({ src, id, anchor }: VideoBlockAttributes) =>
                createBlock(name, {
                    mediaId: id,
                    mediaUrl: src,
                    mediaType: 'video',
                    anchor,
                }),
        },
        {
            type: 'block',
            blocks: ['core/cover'],
            transform: (
                {
                    align,
                    alt,
                    anchor,
                    backgroundType,
                    customGradient,
                    customOverlayColor,
                    gradient,
                    id,
                    overlayColor,
                    style,
                    textColor,
                    url,
                    useFeaturedImage,
                }: CoverBlockAttributes,
                innerBlocks: unknown[]
            ) => {
                let additionalAttributes: Record<string, unknown> = {};

                if (customGradient) {
                    additionalAttributes = {
                        style: {
                            color: {
                                gradient: customGradient,
                            },
                        },
                    };
                } else if (customOverlayColor) {
                    additionalAttributes = {
                        style: {
                            color: {
                                background: customOverlayColor,
                            },
                        },
                    };
                }

                // Maintain custom text color block support value.
                if (style?.color?.text) {
                    const existingStyle =
                        (additionalAttributes.style as
                            | { color?: Record<string, unknown> }
                            | undefined) ?? {};
                    additionalAttributes.style = {
                        color: {
                            ...(existingStyle.color ?? {}),
                            text: style.color.text,
                        },
                    };
                }

                return createBlock(
                    name,
                    {
                        align,
                        anchor,
                        backgroundColor: overlayColor,
                        gradient,
                        mediaAlt: alt,
                        mediaId: id,
                        mediaType: backgroundType,
                        mediaUrl: url,
                        textColor,
                        useFeaturedImage,
                        ...additionalAttributes,
                    },
                    innerBlocks
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/media-text'],
            transform: (
                attributes: MediaTextAttributes,
                innerBlocks: unknown[]
            ) => createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/image'],
            isMatch: ({
                mediaType,
                mediaUrl,
            }: {
                mediaType?: string;
                mediaUrl?: string;
            }) => {
                return !mediaUrl || mediaType === 'image';
            },
            transform: ({
                mediaAlt,
                mediaId,
                mediaUrl,
                anchor,
            }: {
                mediaAlt?: string;
                mediaId?: number;
                mediaUrl?: string;
                anchor?: string;
            }) => {
                return createBlock('core/image', {
                    alt: mediaAlt,
                    id: mediaId,
                    url: mediaUrl,
                    anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/video'],
            isMatch: ({
                mediaType,
                mediaUrl,
            }: {
                mediaType?: string;
                mediaUrl?: string;
            }) => {
                return !mediaUrl || mediaType === 'video';
            },
            transform: ({
                mediaId,
                mediaUrl,
                anchor,
            }: {
                mediaId?: number;
                mediaUrl?: string;
                anchor?: string;
            }) => {
                return createBlock('core/video', {
                    id: mediaId,
                    src: mediaUrl,
                    anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/cover'],
            transform: (
                {
                    align,
                    anchor,
                    backgroundColor,
                    focalPoint,
                    gradient,
                    mediaAlt,
                    mediaId,
                    mediaType,
                    mediaUrl,
                    style,
                    textColor,
                    useFeaturedImage,
                }: {
                    align?: string;
                    anchor?: string;
                    backgroundColor?: string;
                    focalPoint?: unknown;
                    gradient?: string;
                    mediaAlt?: string;
                    mediaId?: number;
                    mediaType?: string;
                    mediaUrl?: string;
                    style?: {
                        color?: {
                            text?: string;
                            background?: string;
                            gradient?: string;
                        };
                    };
                    textColor?: string;
                    useFeaturedImage?: boolean;
                },
                innerBlocks: unknown[]
            ) => {
                const additionalAttributes: Record<string, unknown> = {};

                // Migrate the background styles or gradient to Cover's custom
                // gradient and overlay properties.
                if (style?.color?.gradient) {
                    additionalAttributes.customGradient = style.color.gradient;
                } else if (style?.color?.background) {
                    additionalAttributes.customOverlayColor =
                        style.color.background;
                }

                // Maintain custom text color support style.
                if (style?.color?.text) {
                    additionalAttributes.style = {
                        color: { text: style.color.text },
                    };
                }

                const coverAttributes = {
                    align,
                    alt: mediaAlt,
                    anchor,
                    backgroundType: mediaType,
                    dimRatio: !!mediaUrl || useFeaturedImage ? 50 : 100,
                    focalPoint,
                    gradient,
                    id: mediaId,
                    overlayColor: backgroundColor,
                    textColor,
                    url: mediaUrl,
                    useFeaturedImage,
                    ...additionalAttributes,
                };

                return createBlock(
                    'core/cover',
                    coverAttributes,
                    innerBlocks
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/media-text'],
            transform: (
                attributes: MediaTextAttributes,
                innerBlocks: unknown[]
            ) => createBlock('core/media-text', attributes, innerBlocks),
        },
    ],
};

export default transforms;
