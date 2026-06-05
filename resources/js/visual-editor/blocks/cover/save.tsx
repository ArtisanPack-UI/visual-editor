/**
 * Cover — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/cover/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/cover`. Mixed documents containing
 * `core/cover` and `artisanpack/cover` render to identical HTML.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    useInnerBlocksProps,
    getColorClassName,
    // eslint-disable-next-line camelcase
    __experimentalGetGradientClass,
    useBlockProps,
} from '@wordpress/block-editor';

import {
    IMAGE_BACKGROUND_TYPE,
    VIDEO_BACKGROUND_TYPE,
    EMBED_VIDEO_BACKGROUND_TYPE,
    dimRatioToClass,
    isContentPositionCenter,
    getPositionClassName,
    mediaPosition,
} from './shared';

interface FocalPoint {
    x: number;
    y: number;
}

interface CoverSaveAttributes {
    backgroundType?: string;
    gradient?: string;
    contentPosition?: string;
    customGradient?: string;
    customOverlayColor?: string;
    dimRatio?: number;
    focalPoint?: FocalPoint;
    useFeaturedImage?: boolean;
    hasParallax?: boolean;
    isDark?: boolean;
    isRepeated?: boolean;
    overlayColor?: string;
    url?: string;
    alt?: string;
    id?: number;
    minHeight?: number;
    minHeightUnit?: string;
    tagName?: string;
    sizeSlug?: string;
    poster?: string;
}

interface CoverSaveProps {
    attributes: CoverSaveAttributes;
}

export default function save({ attributes }: CoverSaveProps): ReactElement {
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
        tagName: TagNameProp,
        sizeSlug,
        poster,
    } = attributes;
    const Tag = (TagNameProp ?? 'div') as keyof JSX.IntrinsicElements;
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
    const isEmbedVideoBackground =
        EMBED_VIDEO_BACKGROUND_TYPE === backgroundType;

    const isImgElement = !(hasParallax || isRepeated);

    const style: React.CSSProperties = {
        minHeight: minHeight || undefined,
    };

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
            [`size-${sizeSlug}`]: sizeSlug,
            'has-parallax': hasParallax,
            'is-repeated': isRepeated,
        }
    );

    const gradientValue = gradient || customGradient;

    const innerBlocksSaveProps = useInnerBlocksProps.save({
        className: 'wp-block-cover__inner-container',
    });

    return (
        <Tag {...useBlockProps.save({ className: classes, style })}>
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
                    poster={poster}
                    style={{ objectPosition }}
                    data-object-fit="cover"
                    data-object-position={objectPosition}
                />
            )}
            {isEmbedVideoBackground && url && (
                <figure
                    className={clsx(
                        'wp-block-cover__video-background',
                        'wp-block-cover__embed-background',
                        'wp-block-embed'
                    )}
                >
                    <div className="wp-block-embed__wrapper">{url}</div>
                </figure>
            )}

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

            <div {...innerBlocksSaveProps} />
        </Tag>
    );
}
