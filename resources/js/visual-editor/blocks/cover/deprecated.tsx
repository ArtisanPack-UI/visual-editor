/**
 * Cover — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/cover/deprecated.js` (v9.43.0).
 * Upstream ships a 14-entry chain (v1..v14). All entries are kept verbatim
 * under the new namespace so legacy `core/cover` markup deserializes cleanly
 * after a `core/cover` → `artisanpack/cover` round-trip via the block
 * transforms in `transforms.ts`.
 */

/* eslint-disable camelcase */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { createBlock } from '@wordpress/blocks';
import {
    RichText,
    getColorClassName,
    InnerBlocks,
    __experimentalGetGradientClass,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';

import {
    IMAGE_BACKGROUND_TYPE,
    VIDEO_BACKGROUND_TYPE,
    getPositionClassName,
    isContentPositionCenter,
    dimRatioToClass,
    mediaPosition,
} from './shared';

interface FocalPoint {
    x: number;
    y: number;
}

interface LegacyAttributes {
    [key: string]: unknown;
    url?: string;
    title?: string;
    id?: number;
    alt?: string;
    align?: string;
    contentAlign?: string;
    hasParallax?: boolean;
    isRepeated?: boolean;
    dimRatio?: number;
    overlayColor?: string;
    customOverlayColor?: string;
    backgroundType?: string;
    focalPoint?: FocalPoint;
    minHeight?: number;
    minHeightUnit?: string;
    gradient?: string;
    customGradient?: string;
    contentPosition?: string;
    isDark?: boolean;
    useFeaturedImage?: boolean;
    tagName?: string;
    sizeSlug?: string;
    isUserOverlayColor?: boolean;
}

function backgroundImageStyles(
    url: string | undefined
): React.CSSProperties {
    return url ? { backgroundImage: `url(${url})` } : {};
}

function dimRatioToClassV1(ratio: number | undefined): string | null {
    return ratio === 0 || ratio === 50 || !ratio
        ? null
        : 'has-background-dim-' + 10 * Math.round(ratio / 10);
}

function migrateDimRatio(attributes: LegacyAttributes): LegacyAttributes {
    return {
        ...attributes,
        dimRatio: !attributes.url ? 100 : attributes.dimRatio,
    };
}

function migrateTag(attributes: LegacyAttributes): LegacyAttributes {
    if (!attributes.tagName) {
        attributes = {
            ...attributes,
            tagName: 'div',
        };
    }
    return {
        ...attributes,
    };
}

const blockAttributes = {
    url: { type: 'string' },
    id: { type: 'number' },
    hasParallax: { type: 'boolean', default: false },
    dimRatio: { type: 'number', default: 50 },
    overlayColor: { type: 'string' },
    customOverlayColor: { type: 'string' },
    backgroundType: { type: 'string', default: 'image' },
    focalPoint: { type: 'object' },
};

const v8ToV11BlockAttributes = {
    url: { type: 'string' },
    id: { type: 'number' },
    alt: {
        type: 'string',
        source: 'attribute',
        selector: 'img',
        attribute: 'alt',
        default: '',
    },
    hasParallax: { type: 'boolean', default: false },
    isRepeated: { type: 'boolean', default: false },
    dimRatio: { type: 'number', default: 100 },
    overlayColor: { type: 'string' },
    customOverlayColor: { type: 'string' },
    backgroundType: { type: 'string', default: 'image' },
    focalPoint: { type: 'object' },
    minHeight: { type: 'number' },
    minHeightUnit: { type: 'string' },
    gradient: { type: 'string' },
    customGradient: { type: 'string' },
    contentPosition: { type: 'string' },
    isDark: { type: 'boolean', default: true },
    allowedBlocks: { type: 'array' },
    templateLock: {
        type: ['string', 'boolean'],
        enum: ['all', 'insert', false],
    },
};

const v12toV13BlockAttributes = {
    ...v8ToV11BlockAttributes,
    useFeaturedImage: { type: 'boolean', default: false },
    tagName: { type: 'string', default: 'div' },
};

const v14BlockAttributes = {
    ...v12toV13BlockAttributes,
    isUserOverlayColor: { type: 'boolean' },
    sizeSlug: { type: 'string' },
    alt: { type: 'string', default: '' },
};

const v7toV11BlockSupports = {
    anchor: true,
    align: true,
    html: false,
    spacing: {
        padding: true,
        __experimentalDefaultControls: { padding: true },
    },
    color: {
        __experimentalDuotone:
            '> .wp-block-cover__image-background, > .wp-block-cover__video-background',
        text: false,
        background: false,
    },
};

const v12BlockSupports = {
    ...v7toV11BlockSupports,
    spacing: {
        padding: true,
        margin: ['top', 'bottom'],
        blockGap: true,
        __experimentalDefaultControls: { padding: true, blockGap: true },
    },
    __experimentalBorder: {
        color: true,
        radius: true,
        style: true,
        width: true,
        __experimentalDefaultControls: {
            color: true,
            radius: true,
            style: true,
            width: true,
        },
    },
    color: {
        __experimentalDuotone:
            '> .wp-block-cover__image-background, > .wp-block-cover__video-background',
        heading: true,
        text: true,
        background: false,
        __experimentalSkipSerialization: ['gradients'],
        enableContrastChecker: false,
    },
    typography: {
        fontSize: true,
        lineHeight: true,
        __experimentalFontFamily: true,
        __experimentalFontWeight: true,
        __experimentalFontStyle: true,
        __experimentalTextTransform: true,
        __experimentalTextDecoration: true,
        __experimentalLetterSpacing: true,
        __experimentalDefaultControls: { fontSize: true },
    },
    layout: { allowJustification: false },
};

const v14BlockSupports = {
    ...v12BlockSupports,
    shadow: true,
    dimensions: { aspectRatio: true },
    interactivity: { clientNavigation: true },
};

function renderModernSave({
    attributes,
    backgroundFirst,
    omitSizeSlug,
    omitDivRoleAlt,
    legacyV13Img,
}: {
    attributes: LegacyAttributes;
    backgroundFirst: boolean;
    omitSizeSlug: boolean;
    omitDivRoleAlt: boolean;
    legacyV13Img: boolean;
}): ReactElement {
    const {
        backgroundType,
        gradient,
        contentPosition,
        customGradient,
        customOverlayColor,
        dimRatio,
        focalPoint,
        useFeaturedImage,
        hasParallax,
        isDark,
        isRepeated,
        overlayColor,
        url,
        alt,
        id,
        minHeight: minHeightProp,
        minHeightUnit,
        tagName: tagNameProp,
        sizeSlug,
    } = attributes;
    const Tag = (tagNameProp ?? 'div') as keyof JSX.IntrinsicElements;
    const overlayColorClass = getColorClassName(
        'background-color',
        overlayColor
    );
    const gradientClass = __experimentalGetGradientClass(gradient);
    const minHeight =
        minHeightProp && minHeightUnit
            ? `${minHeightProp}${minHeightUnit}`
            : minHeightProp;

    const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
    const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
    const isImgElement = !(hasParallax || isRepeated);

    const style: React.CSSProperties = { minHeight: minHeight || undefined };

    const bgStyle: React.CSSProperties = {
        backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
        background: customGradient ? customGradient : undefined,
    };

    const objectPosition =
        focalPoint && isImgElement ? mediaPosition(focalPoint) : undefined;

    const backgroundImage = url ? `url(${url})` : undefined;
    const backgroundPosition = mediaPosition(focalPoint);

    const classes = clsx(
        {
            'is-light': !isDark,
            'has-parallax': hasParallax,
            'is-repeated': isRepeated,
            'has-custom-content-position':
                !isContentPositionCenter(contentPosition),
        },
        getPositionClassName(contentPosition)
    );

    const imgClasses = clsx(
        'wp-block-cover__image-background',
        id ? `wp-image-${id}` : null,
        {
            ...(!omitSizeSlug && sizeSlug
                ? { [`size-${sizeSlug}`]: sizeSlug }
                : {}),
            'has-parallax': hasParallax,
            'is-repeated': isRepeated,
        }
    );

    const gradientValue = gradient || customGradient;

    const overlaySpan = (
        <span
            aria-hidden="true"
            className={clsx(
                'wp-block-cover__background',
                overlayColorClass,
                dimRatioToClass(dimRatio),
                {
                    'has-background-dim': dimRatio !== undefined,
                    'wp-block-cover__gradient-background':
                        !!url && !!gradientValue && dimRatio !== 0,
                    'has-background-gradient': !!gradientValue,
                    [gradientClass as string]: !!gradientClass,
                }
            )}
            style={bgStyle}
        />
    );

    const mediaEls = (
        <>
            {!useFeaturedImage &&
                isImageBackground &&
                url &&
                (isImgElement ? (
                    <img
                        className={imgClasses}
                        alt={alt}
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                ) : legacyV13Img || omitDivRoleAlt ? (
                    <div
                        role="img"
                        className={imgClasses}
                        style={{ backgroundPosition, backgroundImage }}
                    />
                ) : (
                    <div
                        role={alt ? 'img' : undefined}
                        aria-label={alt ? alt : undefined}
                        className={imgClasses}
                        style={{ backgroundPosition, backgroundImage }}
                    />
                ))}
            {isVideoBackground && url && (
                <video
                    className={clsx(
                        'wp-block-cover__video-background',
                        'intrinsic-ignore'
                    )}
                    autoPlay
                    muted
                    loop
                    playsInline
                    src={url}
                    style={{ objectPosition }}
                    data-object-fit="cover"
                    data-object-position={objectPosition}
                />
            )}
        </>
    );

    const innerBlocksSaveProps = useInnerBlocksProps.save({
        className: 'wp-block-cover__inner-container',
    });

    return (
        <Tag {...useBlockProps.save({ className: classes, style })}>
            {backgroundFirst && overlaySpan}
            {mediaEls}
            {!backgroundFirst && overlaySpan}
            <div {...innerBlocksSaveProps} />
        </Tag>
    );
}

const v14 = {
    attributes: v14BlockAttributes,
    supports: v14BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        return renderModernSave({
            attributes,
            backgroundFirst: true,
            omitSizeSlug: false,
            omitDivRoleAlt: false,
            legacyV13Img: false,
        });
    },
};

const v13 = {
    attributes: v12toV13BlockAttributes,
    supports: v12BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        return renderModernSave({
            attributes,
            backgroundFirst: false,
            omitSizeSlug: true,
            omitDivRoleAlt: true,
            legacyV13Img: true,
        });
    },
};

const v12 = {
    attributes: v12toV13BlockAttributes,
    supports: v12BlockSupports,
    isEligible(attributes: LegacyAttributes): boolean {
        return (
            (attributes.customOverlayColor !== undefined ||
                attributes.overlayColor !== undefined) &&
            attributes.isUserOverlayColor === undefined
        );
    },
    migrate(attributes: LegacyAttributes): LegacyAttributes {
        return { ...attributes, isUserOverlayColor: true };
    },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        return renderModernSave({
            attributes,
            backgroundFirst: false,
            omitSizeSlug: true,
            omitDivRoleAlt: true,
            legacyV13Img: true,
        });
    },
};

const v11 = {
    attributes: v8ToV11BlockAttributes,
    supports: v7toV11BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        return renderModernSave({
            attributes: { ...attributes, tagName: 'div' },
            backgroundFirst: false,
            omitSizeSlug: true,
            omitDivRoleAlt: true,
            legacyV13Img: true,
        });
    },
    migrate: migrateTag,
};

const v10 = {
    attributes: v8ToV11BlockAttributes,
    supports: v7toV11BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            contentPosition,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            useFeaturedImage,
            hasParallax,
            isDark,
            isRepeated,
            overlayColor,
            url,
            alt,
            id,
            minHeight: minHeightProp,
            minHeightUnit,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const minHeight =
            minHeightProp && minHeightUnit
                ? `${minHeightProp}${minHeightUnit}`
                : minHeightProp;
        const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
        const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
        const isImgElement = !(hasParallax || isRepeated);
        const style: React.CSSProperties = {
            ...(isImageBackground && !isImgElement && !useFeaturedImage
                ? backgroundImageStyles(url)
                : {}),
            minHeight: minHeight || undefined,
        };
        const bgStyle: React.CSSProperties = {
            backgroundColor: !overlayColorClass
                ? customOverlayColor
                : undefined,
            background: customGradient ? customGradient : undefined,
        };
        const objectPosition =
            focalPoint && isImgElement
                ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`
                : undefined;
        const classes = clsx(
            {
                'is-light': !isDark,
                'has-parallax': hasParallax,
                'is-repeated': isRepeated,
                'has-custom-content-position':
                    !isContentPositionCenter(contentPosition),
            },
            getPositionClassName(contentPosition)
        );
        const gradientValue = gradient || customGradient;
        const innerBlocksSaveProps = useInnerBlocksProps.save({
            className: 'wp-block-cover__inner-container',
        });
        return (
            <div {...useBlockProps.save({ className: classes, style })}>
                <span
                    aria-hidden="true"
                    className={clsx(
                        'wp-block-cover__background',
                        overlayColorClass,
                        dimRatioToClass(dimRatio),
                        {
                            'has-background-dim': dimRatio !== undefined,
                            'wp-block-cover__gradient-background':
                                !!url && !!gradientValue && dimRatio !== 0,
                            'has-background-gradient': !!gradientValue,
                            [gradientClass as string]: !!gradientClass,
                        }
                    )}
                    style={bgStyle}
                />
                {!useFeaturedImage &&
                    isImageBackground &&
                    isImgElement &&
                    url && (
                        <img
                            className={clsx(
                                'wp-block-cover__image-background',
                                id ? `wp-image-${id}` : null
                            )}
                            alt={alt}
                            src={url}
                            style={{ objectPosition }}
                            data-object-fit="cover"
                            data-object-position={objectPosition}
                        />
                    )}
                {isVideoBackground && url && (
                    <video
                        className={clsx(
                            'wp-block-cover__video-background',
                            'intrinsic-ignore'
                        )}
                        autoPlay
                        muted
                        loop
                        playsInline
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                <div {...innerBlocksSaveProps} />
            </div>
        );
    },
    migrate: migrateTag,
};

const v9 = {
    attributes: v8ToV11BlockAttributes,
    supports: v7toV11BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            contentPosition,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            isDark,
            isRepeated,
            overlayColor,
            url,
            alt,
            id,
            minHeight: minHeightProp,
            minHeightUnit,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const minHeight = minHeightUnit
            ? `${minHeightProp}${minHeightUnit}`
            : minHeightProp;
        const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
        const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
        const isImgElement = !(hasParallax || isRepeated);
        const style: React.CSSProperties = {
            ...(isImageBackground && !isImgElement
                ? backgroundImageStyles(url)
                : {}),
            minHeight: minHeight || undefined,
        };
        const bgStyle: React.CSSProperties = {
            backgroundColor: !overlayColorClass
                ? customOverlayColor
                : undefined,
            background: customGradient ? customGradient : undefined,
        };
        const objectPosition =
            focalPoint && isImgElement
                ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`
                : undefined;
        const classes = clsx(
            {
                'is-light': !isDark,
                'has-parallax': hasParallax,
                'is-repeated': isRepeated,
                'has-custom-content-position':
                    !isContentPositionCenter(contentPosition),
            },
            getPositionClassName(contentPosition)
        );
        const gradientValue = gradient || customGradient;
        const innerBlocksSaveProps = useInnerBlocksProps.save({
            className: 'wp-block-cover__inner-container',
        });
        return (
            <div {...useBlockProps.save({ className: classes, style })}>
                <span
                    aria-hidden="true"
                    className={clsx(
                        'wp-block-cover__background',
                        overlayColorClass,
                        dimRatioToClass(dimRatio),
                        {
                            'has-background-dim': dimRatio !== undefined,
                            'wp-block-cover__gradient-background':
                                !!url && !!gradientValue && dimRatio !== 0,
                            'has-background-gradient': !!gradientValue,
                            [gradientClass as string]: !!gradientClass,
                        }
                    )}
                    style={bgStyle}
                />
                {isImageBackground && isImgElement && url && (
                    <img
                        className={clsx(
                            'wp-block-cover__image-background',
                            id ? `wp-image-${id}` : null
                        )}
                        alt={alt}
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                {isVideoBackground && url && (
                    <video
                        className={clsx(
                            'wp-block-cover__video-background',
                            'intrinsic-ignore'
                        )}
                        autoPlay
                        muted
                        loop
                        playsInline
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                <div {...innerBlocksSaveProps} />
            </div>
        );
    },
    migrate: migrateTag,
};

const v8 = {
    attributes: v8ToV11BlockAttributes,
    supports: v7toV11BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            contentPosition,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            isDark,
            isRepeated,
            overlayColor,
            url,
            alt,
            id,
            minHeight: minHeightProp,
            minHeightUnit,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const minHeight = minHeightUnit
            ? `${minHeightProp}${minHeightUnit}`
            : minHeightProp;
        const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
        const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
        const isImgElement = !(hasParallax || isRepeated);
        const style: React.CSSProperties = {
            ...(isImageBackground && !isImgElement
                ? backgroundImageStyles(url)
                : {}),
            minHeight: minHeight || undefined,
        };
        const bgStyle: React.CSSProperties = {
            backgroundColor: !overlayColorClass
                ? customOverlayColor
                : undefined,
            background: customGradient ? customGradient : undefined,
        };
        const objectPosition =
            focalPoint && isImgElement
                ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`
                : undefined;
        const classes = clsx(
            {
                'is-light': !isDark,
                'has-parallax': hasParallax,
                'is-repeated': isRepeated,
                'has-custom-content-position':
                    !isContentPositionCenter(contentPosition),
            },
            getPositionClassName(contentPosition)
        );
        const innerBlocksSaveProps = useInnerBlocksProps.save({
            className: 'wp-block-cover__inner-container',
        });
        return (
            <div {...useBlockProps.save({ className: classes, style })}>
                <span
                    aria-hidden="true"
                    className={clsx(
                        overlayColorClass,
                        dimRatioToClass(dimRatio),
                        'wp-block-cover__gradient-background',
                        gradientClass,
                        {
                            'has-background-dim': dimRatio !== undefined,
                            'has-background-gradient': !!(
                                gradient || customGradient
                            ),
                            [gradientClass as string]: !url && !!gradientClass,
                        }
                    )}
                    style={bgStyle}
                />
                {isImageBackground && isImgElement && url && (
                    <img
                        className={clsx(
                            'wp-block-cover__image-background',
                            id ? `wp-image-${id}` : null
                        )}
                        alt={alt}
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                {isVideoBackground && url && (
                    <video
                        className={clsx(
                            'wp-block-cover__video-background',
                            'intrinsic-ignore'
                        )}
                        autoPlay
                        muted
                        loop
                        playsInline
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                <div {...innerBlocksSaveProps} />
            </div>
        );
    },
    migrate: migrateTag,
};

const v7 = {
    attributes: {
        ...blockAttributes,
        isRepeated: { type: 'boolean', default: false },
        minHeight: { type: 'number' },
        minHeightUnit: { type: 'string' },
        gradient: { type: 'string' },
        customGradient: { type: 'string' },
        contentPosition: { type: 'string' },
        alt: {
            type: 'string',
            source: 'attribute',
            selector: 'img',
            attribute: 'alt',
            default: '',
        },
    },
    supports: v7toV11BlockSupports,
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            contentPosition,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            isRepeated,
            overlayColor,
            url,
            alt,
            id,
            minHeight: minHeightProp,
            minHeightUnit,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const minHeight = minHeightUnit
            ? `${minHeightProp}${minHeightUnit}`
            : minHeightProp;
        const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
        const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
        const isImgElement = !(hasParallax || isRepeated);
        const style: React.CSSProperties = {
            ...(isImageBackground && !isImgElement
                ? backgroundImageStyles(url)
                : {}),
            backgroundColor: !overlayColorClass ? customOverlayColor : undefined,
            background: customGradient && !url ? customGradient : undefined,
            minHeight: minHeight || undefined,
        };
        const objectPosition =
            focalPoint && isImgElement
                ? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`
                : undefined;
        const classes = clsx(
            dimRatioToClassV1(dimRatio),
            overlayColorClass,
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
                'is-repeated': !!isRepeated,
                'has-background-gradient': !!(gradient || customGradient),
                [gradientClass as string]: !url && !!gradientClass,
                'has-custom-content-position':
                    !isContentPositionCenter(contentPosition),
            },
            getPositionClassName(contentPosition)
        );
        return (
            <div {...useBlockProps.save({ className: classes, style })}>
                {url && (gradient || customGradient) && dimRatio !== 0 && (
                    <span
                        aria-hidden="true"
                        className={clsx(
                            'wp-block-cover__gradient-background',
                            gradientClass
                        )}
                        style={
                            customGradient
                                ? { background: customGradient }
                                : undefined
                        }
                    />
                )}
                {isImageBackground && isImgElement && url && (
                    <img
                        className={clsx(
                            'wp-block-cover__image-background',
                            id ? `wp-image-${id}` : null
                        )}
                        alt={alt}
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                {isVideoBackground && url && (
                    <video
                        className={clsx(
                            'wp-block-cover__video-background',
                            'intrinsic-ignore'
                        )}
                        autoPlay
                        muted
                        loop
                        playsInline
                        src={url}
                        style={{ objectPosition }}
                        data-object-fit="cover"
                        data-object-position={objectPosition}
                    />
                )}
                <div className="wp-block-cover__inner-container">
                    <InnerBlocks.Content />
                </div>
            </div>
        );
    },
    migrate: compose(migrateDimRatio, migrateTag),
};

const v6 = {
    attributes: {
        ...blockAttributes,
        isRepeated: { type: 'boolean', default: false },
        minHeight: { type: 'number' },
        minHeightUnit: { type: 'string' },
        gradient: { type: 'string' },
        customGradient: { type: 'string' },
        contentPosition: { type: 'string' },
    },
    supports: { align: true },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            contentPosition,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            isRepeated,
            overlayColor,
            url,
            minHeight: minHeightProp,
            minHeightUnit,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const minHeight = minHeightUnit
            ? `${minHeightProp}${minHeightUnit}`
            : minHeightProp;
        const isImageBackground = IMAGE_BACKGROUND_TYPE === backgroundType;
        const isVideoBackground = VIDEO_BACKGROUND_TYPE === backgroundType;
        const style: React.CSSProperties = isImageBackground
            ? backgroundImageStyles(url)
            : {};
        const videoStyle: React.CSSProperties = {};
        if (!overlayColorClass) {
            style.backgroundColor = customOverlayColor;
        }
        if (customGradient && !url) {
            style.background = customGradient;
        }
        style.minHeight = minHeight || undefined;
        let positionValue: string | undefined;
        if (focalPoint) {
            positionValue = `${Math.round(
                focalPoint.x * 100
            )}% ${Math.round(focalPoint.y * 100)}%`;
            if (isImageBackground && !hasParallax) {
                style.backgroundPosition = positionValue;
            }
            if (isVideoBackground) {
                videoStyle.objectPosition = positionValue;
            }
        }
        const classes = clsx(
            dimRatioToClassV1(dimRatio),
            overlayColorClass,
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
                'is-repeated': !!isRepeated,
                'has-background-gradient': !!(gradient || customGradient),
                [gradientClass as string]: !url && !!gradientClass,
                'has-custom-content-position':
                    !isContentPositionCenter(contentPosition),
            },
            getPositionClassName(contentPosition)
        );
        return (
            <div {...useBlockProps.save({ className: classes, style })}>
                {url && (gradient || customGradient) && dimRatio !== 0 && (
                    <span
                        aria-hidden="true"
                        className={clsx(
                            'wp-block-cover__gradient-background',
                            gradientClass
                        )}
                        style={
                            customGradient
                                ? { background: customGradient }
                                : undefined
                        }
                    />
                )}
                {isVideoBackground && url && (
                    <video
                        className="wp-block-cover__video-background"
                        autoPlay
                        muted
                        loop
                        playsInline
                        src={url}
                        style={videoStyle}
                    />
                )}
                <div className="wp-block-cover__inner-container">
                    <InnerBlocks.Content />
                </div>
            </div>
        );
    },
    migrate: compose(migrateDimRatio, migrateTag),
};

const v5 = {
    attributes: {
        ...blockAttributes,
        minHeight: { type: 'number' },
        gradient: { type: 'string' },
        customGradient: { type: 'string' },
    },
    supports: { align: true },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            overlayColor,
            url,
            minHeight,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const style: React.CSSProperties =
            backgroundType === IMAGE_BACKGROUND_TYPE
                ? backgroundImageStyles(url)
                : {};
        if (!overlayColorClass) {
            style.backgroundColor = customOverlayColor;
        }
        if (focalPoint && !hasParallax) {
            style.backgroundPosition = `${Math.round(
                focalPoint.x * 100
            )}% ${Math.round(focalPoint.y * 100)}%`;
        }
        if (customGradient && !url) {
            style.background = customGradient;
        }
        style.minHeight = minHeight || undefined;
        const classes = clsx(
            dimRatioToClassV1(dimRatio),
            overlayColorClass,
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
                'has-background-gradient': !!customGradient,
                [gradientClass as string]: !url && !!gradientClass,
            }
        );
        return (
            <div className={classes} style={style}>
                {url && (gradient || customGradient) && dimRatio !== 0 && (
                    <span
                        aria-hidden="true"
                        className={clsx(
                            'wp-block-cover__gradient-background',
                            gradientClass
                        )}
                        style={
                            customGradient
                                ? { background: customGradient }
                                : undefined
                        }
                    />
                )}
                {VIDEO_BACKGROUND_TYPE === backgroundType && url && (
                    <video
                        className="wp-block-cover__video-background"
                        autoPlay
                        muted
                        loop
                        src={url}
                    />
                )}
                <div className="wp-block-cover__inner-container">
                    <InnerBlocks.Content />
                </div>
            </div>
        );
    },
    migrate: compose(migrateDimRatio, migrateTag),
};

const v4 = {
    attributes: {
        ...blockAttributes,
        minHeight: { type: 'number' },
        gradient: { type: 'string' },
        customGradient: { type: 'string' },
    },
    supports: { align: true },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            gradient,
            customGradient,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            overlayColor,
            url,
            minHeight,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const gradientClass = __experimentalGetGradientClass(gradient);
        const style: React.CSSProperties =
            backgroundType === IMAGE_BACKGROUND_TYPE
                ? backgroundImageStyles(url)
                : {};
        if (!overlayColorClass) {
            style.backgroundColor = customOverlayColor;
        }
        if (focalPoint && !hasParallax) {
            style.backgroundPosition = `${focalPoint.x * 100}% ${
                focalPoint.y * 100
            }%`;
        }
        if (customGradient && !url) {
            style.background = customGradient;
        }
        style.minHeight = minHeight || undefined;
        const classes = clsx(
            dimRatioToClassV1(dimRatio),
            overlayColorClass,
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
                'has-background-gradient': !!customGradient,
                [gradientClass as string]: !url && !!gradientClass,
            }
        );
        return (
            <div className={classes} style={style}>
                {url && (gradient || customGradient) && dimRatio !== 0 && (
                    <span
                        aria-hidden="true"
                        className={clsx(
                            'wp-block-cover__gradient-background',
                            gradientClass
                        )}
                        style={
                            customGradient
                                ? { background: customGradient }
                                : undefined
                        }
                    />
                )}
                {VIDEO_BACKGROUND_TYPE === backgroundType && url && (
                    <video
                        className="wp-block-cover__video-background"
                        autoPlay
                        muted
                        loop
                        src={url}
                    />
                )}
                <div className="wp-block-cover__inner-container">
                    <InnerBlocks.Content />
                </div>
            </div>
        );
    },
    migrate: compose(migrateDimRatio, migrateTag),
};

const v3 = {
    attributes: {
        ...blockAttributes,
        title: { type: 'string', source: 'html', selector: 'p' },
        contentAlign: { type: 'string', default: 'center' },
    },
    supports: { align: true },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            backgroundType,
            contentAlign,
            customOverlayColor,
            dimRatio,
            focalPoint,
            hasParallax,
            overlayColor,
            title,
            url,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const style: React.CSSProperties =
            backgroundType === IMAGE_BACKGROUND_TYPE
                ? backgroundImageStyles(url)
                : {};
        if (!overlayColorClass) {
            style.backgroundColor = customOverlayColor;
        }
        if (focalPoint && !hasParallax) {
            style.backgroundPosition = `${focalPoint.x * 100}% ${
                focalPoint.y * 100
            }%`;
        }
        const classes = clsx(
            dimRatioToClassV1(dimRatio),
            overlayColorClass,
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
                [`has-${contentAlign}-content`]: contentAlign !== 'center',
            }
        );
        return (
            <div className={classes} style={style}>
                {VIDEO_BACKGROUND_TYPE === backgroundType && url && (
                    <video
                        className="wp-block-cover__video-background"
                        autoPlay
                        muted
                        loop
                        src={url}
                    />
                )}
                {!RichText.isEmpty(title ?? '') && (
                    <RichText.Content
                        tagName="p"
                        className="wp-block-cover-text"
                        value={title}
                    />
                )}
            </div>
        );
    },
    migrate(attributes: LegacyAttributes) {
        const newAttribs = {
            ...attributes,
            dimRatio: !attributes.url ? 100 : attributes.dimRatio,
            tagName: !attributes.tagName ? 'div' : attributes.tagName,
        };
        const { title, contentAlign, ...restAttributes } = newAttribs;
        return [
            restAttributes,
            [
                createBlock('core/paragraph', {
                    content: attributes.title,
                    style: {
                        typography: {
                            textAlign: attributes.contentAlign,
                        },
                    },
                    fontSize: 'large',
                    placeholder: __('Write title…'),
                }),
            ],
        ];
    },
};

const v2 = {
    attributes: {
        ...blockAttributes,
        title: { type: 'string', source: 'html', selector: 'p' },
        contentAlign: { type: 'string', default: 'center' },
        align: { type: 'string' },
    },
    supports: { className: false },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const {
            url,
            title,
            hasParallax,
            dimRatio,
            align,
            contentAlign,
            overlayColor,
            customOverlayColor,
        } = attributes;
        const overlayColorClass = getColorClassName(
            'background-color',
            overlayColor
        );
        const style: React.CSSProperties = backgroundImageStyles(url);
        if (!overlayColorClass) {
            style.backgroundColor = customOverlayColor;
        }
        const classes = clsx(
            'wp-block-cover-image',
            dimRatioToClassV1(dimRatio),
            overlayColorClass,
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
                [`has-${contentAlign}-content`]: contentAlign !== 'center',
            },
            align ? `align${align}` : null
        );
        return (
            <div className={classes} style={style}>
                {!RichText.isEmpty(title ?? '') && (
                    <RichText.Content
                        tagName="p"
                        className="wp-block-cover-image-text"
                        value={title}
                    />
                )}
            </div>
        );
    },
    migrate(attributes: LegacyAttributes) {
        const newAttribs = {
            ...attributes,
            dimRatio: !attributes.url ? 100 : attributes.dimRatio,
            tagName: !attributes.tagName ? 'div' : attributes.tagName,
        };
        const { title, contentAlign, align, ...restAttributes } = newAttribs;
        return [
            restAttributes,
            [
                createBlock('core/paragraph', {
                    content: attributes.title,
                    style: {
                        typography: {
                            textAlign: attributes.contentAlign,
                        },
                    },
                    fontSize: 'large',
                    placeholder: __('Write title…'),
                }),
            ],
        ];
    },
};

const v1 = {
    attributes: {
        ...blockAttributes,
        title: { type: 'string', source: 'html', selector: 'h2' },
        align: { type: 'string' },
        contentAlign: { type: 'string', default: 'center' },
    },
    supports: { className: false },
    save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
        const { url, title, hasParallax, dimRatio, align } = attributes;
        const style = backgroundImageStyles(url);
        const classes = clsx(
            'wp-block-cover-image',
            dimRatioToClassV1(dimRatio),
            {
                'has-background-dim': dimRatio !== 0,
                'has-parallax': !!hasParallax,
            },
            align ? `align${align}` : null
        );
        return (
            <section className={classes} style={style}>
                <RichText.Content tagName="h2" value={title} />
            </section>
        );
    },
    migrate(attributes: LegacyAttributes) {
        const newAttribs = {
            ...attributes,
            dimRatio: !attributes.url ? 100 : attributes.dimRatio,
            tagName: !attributes.tagName ? 'div' : attributes.tagName,
        };
        const { title, contentAlign, align, ...restAttributes } = newAttribs;
        return [
            restAttributes,
            [
                createBlock('core/paragraph', {
                    content: attributes.title,
                    style: {
                        typography: {
                            textAlign: attributes.contentAlign,
                        },
                    },
                    fontSize: 'large',
                    placeholder: __('Write title…'),
                }),
            ],
        ];
    },
};

export default [v14, v13, v12, v11, v10, v9, v8, v7, v6, v5, v4, v3, v2, v1];
