/**
 * Cover — transforms.
 *
 * Ported from `@wordpress/block-library/src/cover/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/cover` ↔
 * `artisanpack/cover` so mixed documents round-trip losslessly during the
 * V2 rollout.
 *
 * Divergences (documented in `upstream-state.json` under `knownDivergences`):
 *   - Upstream calls `cleanEmptyObject` via
 *     `unlock(blockEditorPrivateApis)`. The fork inlines an equivalent
 *     recursive prune helper so it does not depend on the private API.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';
import { IMAGE_BACKGROUND_TYPE, VIDEO_BACKGROUND_TYPE } from './shared';

const { name } = metadata;

interface CoverAttributes {
    url?: string;
    useFeaturedImage?: boolean;
    backgroundType?: string;
    overlayColor?: string;
    customOverlayColor?: string;
    gradient?: string;
    customGradient?: string;
    align?: string;
    anchor?: string;
    id?: number;
    alt?: string;
    title?: string;
    dimRatio?: number;
    style?: Record<string, unknown>;
    [key: string]: unknown;
}

interface GroupAttributes {
    align?: string;
    anchor?: string;
    backgroundColor?: string;
    gradient?: string;
    style?: {
        color?: {
            background?: string;
            gradient?: string;
            duotone?: unknown;
        };
    };
    [key: string]: unknown;
}

interface InnerBlock {
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks?: InnerBlock[];
}

/**
 * Inline replacement for `cleanEmptyObject` from
 * `unlock(@wordpress/block-editor.privateApis)`. Recursively prunes
 * `undefined`/empty-object values; returns `undefined` when the result
 * is empty.
 */
function cleanEmptyObject<T>(object: T): T | undefined {
    if (
        object === null ||
        object === undefined ||
        typeof object !== 'object' ||
        Array.isArray(object)
    ) {
        return object;
    }

    const cleaned = Object.entries(object as Record<string, unknown>)
        .map(([key, value]) => [key, cleanEmptyObject(value)] as const)
        .filter(([, value]) => value !== undefined);

    if (cleaned.length === 0) {
        return undefined;
    }

    return Object.fromEntries(cleaned) as unknown as T;
}

const transforms = {
    from: [
        {
            type: 'block',
            blocks: ['core/image'],
            transform: ({
                caption,
                url,
                alt,
                align,
                id,
                anchor,
                style,
            }: {
                caption?: string;
                url?: string;
                alt?: string;
                align?: string;
                id?: number;
                anchor?: string;
                style?: { color?: { duotone?: unknown } };
            }) =>
                createBlock(
                    name,
                    {
                        dimRatio: 50,
                        url,
                        alt,
                        align,
                        id,
                        anchor,
                        style: {
                            color: {
                                duotone: style?.color?.duotone,
                            },
                        },
                    },
                    [
                        createBlock('core/paragraph', {
                            content: caption,
                            fontSize: 'large',
                            style: {
                                typography: {
                                    textAlign: 'center',
                                },
                            },
                        }),
                    ]
                ),
        },
        {
            type: 'block',
            blocks: ['core/video'],
            transform: ({
                caption,
                src,
                align,
                id,
                anchor,
            }: {
                caption?: string;
                src?: string;
                align?: string;
                id?: number;
                anchor?: string;
            }) =>
                createBlock(
                    name,
                    {
                        dimRatio: 50,
                        url: src,
                        align,
                        id,
                        backgroundType: VIDEO_BACKGROUND_TYPE,
                        anchor,
                    },
                    [
                        createBlock('core/paragraph', {
                            content: caption,
                            fontSize: 'large',
                            style: {
                                typography: {
                                    textAlign: 'center',
                                },
                            },
                        }),
                    ]
                ),
        },
        {
            type: 'block',
            blocks: ['core/group'],
            transform: (
                attributes: GroupAttributes,
                innerBlocks: InnerBlock[]
            ) => {
                const { align, anchor, backgroundColor, gradient, style } =
                    attributes;

                if (
                    innerBlocks?.length === 1 &&
                    (innerBlocks[0]?.name === 'core/cover' ||
                        innerBlocks[0]?.name === name)
                ) {
                    return createBlock(
                        name,
                        innerBlocks[0].attributes,
                        innerBlocks[0].innerBlocks
                    );
                }

                const dimRatio =
                    backgroundColor ||
                    gradient ||
                    style?.color?.background ||
                    style?.color?.gradient
                        ? undefined
                        : 50;

                const parentAttributes = {
                    align,
                    anchor,
                    dimRatio,
                    overlayColor: backgroundColor,
                    customOverlayColor: style?.color?.background,
                    gradient,
                    customGradient: style?.color?.gradient,
                };

                const attributesWithoutBackgroundColors = {
                    ...attributes,
                    backgroundColor: undefined,
                    gradient: undefined,
                    style: cleanEmptyObject({
                        ...attributes?.style,
                        color: style?.color
                            ? {
                                  ...style?.color,
                                  background: undefined,
                                  gradient: undefined,
                              }
                            : undefined,
                    }),
                };

                return createBlock(name, parentAttributes, [
                    createBlock(
                        'core/group',
                        attributesWithoutBackgroundColors,
                        innerBlocks
                    ),
                ]);
            },
        },
        {
            type: 'block',
            blocks: ['core/cover'],
            transform: (attributes: CoverAttributes, innerBlocks: InnerBlock[]) =>
                createBlock(name, attributes, innerBlocks ?? []),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/image'],
            isMatch: ({
                backgroundType,
                url,
                overlayColor,
                customOverlayColor,
                gradient,
                customGradient,
            }: CoverAttributes): boolean => {
                if (url) {
                    return backgroundType === IMAGE_BACKGROUND_TYPE;
                }
                return (
                    !overlayColor &&
                    !customOverlayColor &&
                    !gradient &&
                    !customGradient
                );
            },
            transform: ({
                title,
                url,
                alt,
                align,
                id,
                anchor,
                style,
            }: CoverAttributes) =>
                createBlock('core/image', {
                    caption: title,
                    url,
                    alt,
                    align,
                    id,
                    anchor,
                    style: {
                        color: {
                            duotone: (style as { color?: { duotone?: unknown } })
                                ?.color?.duotone,
                        },
                    },
                }),
        },
        {
            type: 'block',
            blocks: ['core/video'],
            isMatch: ({
                backgroundType,
                url,
                overlayColor,
                customOverlayColor,
                gradient,
                customGradient,
            }: CoverAttributes): boolean => {
                if (url) {
                    return backgroundType === VIDEO_BACKGROUND_TYPE;
                }
                return (
                    !overlayColor &&
                    !customOverlayColor &&
                    !gradient &&
                    !customGradient
                );
            },
            transform: ({ title, url, align, id, anchor }: CoverAttributes) =>
                createBlock('core/video', {
                    caption: title,
                    src: url,
                    id,
                    align,
                    anchor,
                }),
        },
        {
            type: 'block',
            blocks: ['core/group'],
            isMatch: ({ url, useFeaturedImage }: CoverAttributes): boolean => {
                if (url || useFeaturedImage) {
                    return false;
                }
                return true;
            },
            transform: (
                attributes: CoverAttributes,
                innerBlocks: InnerBlock[]
            ) => {
                const transformedColorAttributes = {
                    backgroundColor: attributes?.overlayColor,
                    gradient: attributes?.gradient,
                    style: cleanEmptyObject({
                        ...attributes?.style,
                        color:
                            attributes?.customOverlayColor ||
                            attributes?.customGradient ||
                            (attributes?.style as { color?: unknown })?.color
                                ? {
                                      background:
                                          attributes?.customOverlayColor,
                                      gradient: attributes?.customGradient,
                                      ...(attributes?.style as {
                                          color?: Record<string, unknown>;
                                      })?.color,
                                  }
                                : undefined,
                    }),
                };

                if (
                    innerBlocks?.length === 1 &&
                    innerBlocks[0]?.name === 'core/group'
                ) {
                    const groupAttributes = cleanEmptyObject(
                        innerBlocks[0].attributes || {}
                    ) as GroupAttributes | undefined;

                    if (
                        groupAttributes?.backgroundColor ||
                        groupAttributes?.gradient ||
                        groupAttributes?.style?.color?.background ||
                        groupAttributes?.style?.color?.gradient
                    ) {
                        return createBlock(
                            'core/group',
                            groupAttributes ?? {},
                            innerBlocks[0]?.innerBlocks
                        );
                    }

                    return createBlock(
                        'core/group',
                        {
                            ...transformedColorAttributes,
                            ...(groupAttributes ?? {}),
                            style: cleanEmptyObject({
                                ...(groupAttributes?.style as Record<
                                    string,
                                    unknown
                                >),
                                color:
                                    (
                                        transformedColorAttributes?.style as {
                                            color?: unknown;
                                        }
                                    )?.color ||
                                    groupAttributes?.style?.color
                                        ? {
                                              ...((
                                                  transformedColorAttributes?.style as {
                                                      color?: Record<
                                                          string,
                                                          unknown
                                                      >;
                                                  }
                                              )?.color ?? {}),
                                              ...(groupAttributes?.style
                                                  ?.color ?? {}),
                                          }
                                        : undefined,
                            }),
                        },
                        innerBlocks[0]?.innerBlocks
                    );
                }

                return createBlock(
                    'core/group',
                    { ...attributes, ...transformedColorAttributes },
                    innerBlocks
                );
            },
        },
        {
            type: 'block',
            blocks: ['core/cover'],
            transform: (attributes: CoverAttributes, innerBlocks: InnerBlock[]) =>
                createBlock('core/cover', attributes, innerBlocks ?? []),
        },
    ],
};

export default transforms;
