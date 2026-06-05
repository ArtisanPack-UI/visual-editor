/**
 * Media & Text — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/media-text/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/media-text`. Mixed documents containing
 * `core/media-text` and `artisanpack/media-text` render to identical HTML.
 */

import type { CSSProperties, ReactElement, ReactNode } from 'react';
import clsx from 'clsx';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import { imageFillStyles, type FocalPoint } from './image-fill';
import { DEFAULT_MEDIA_SIZE_SLUG } from './constants';

const DEFAULT_MEDIA_WIDTH = 50;
const noop = (): null => null;

interface MediaTextAttributes {
    readonly isStackedOnMobile?: boolean;
    readonly mediaAlt?: string;
    readonly mediaPosition?: string;
    readonly mediaType?: string;
    readonly mediaUrl?: string;
    readonly mediaWidth?: number;
    readonly mediaId?: number;
    readonly mediaSizeSlug?: string;
    readonly verticalAlignment?: string;
    readonly imageFill?: boolean;
    readonly focalPoint?: FocalPoint;
    readonly linkClass?: string;
    readonly href?: string;
    readonly linkTarget?: string;
    readonly rel?: string;
}

interface MediaTextSaveProps {
    readonly attributes: MediaTextAttributes;
}

export default function save({ attributes }: MediaTextSaveProps): ReactElement {
    const {
        isStackedOnMobile,
        mediaAlt,
        mediaPosition,
        mediaType,
        mediaUrl,
        mediaWidth,
        mediaId,
        verticalAlignment,
        imageFill,
        focalPoint,
        linkClass,
        href,
        linkTarget,
        rel,
    } = attributes;
    const mediaSizeSlug = attributes.mediaSizeSlug || DEFAULT_MEDIA_SIZE_SLUG;
    const newRel = !rel ? undefined : rel;

    const imageClasses = clsx({
        [`wp-image-${mediaId}`]: mediaId && mediaType === 'image',
        [`size-${mediaSizeSlug}`]: mediaId && mediaType === 'image',
    });

    const positionStyles: CSSProperties = imageFill
        ? (imageFillStyles(mediaUrl, focalPoint) as CSSProperties)
        : {};

    let image: ReactNode = mediaUrl ? (
        <img
            src={mediaUrl}
            alt={mediaAlt}
            className={imageClasses || undefined}
            style={positionStyles}
        />
    ) : null;

    if (href) {
        image = (
            <a
                className={linkClass}
                href={href}
                target={linkTarget}
                rel={newRel}
            >
                {image}
            </a>
        );
    }

    const mediaTypeRenders: Record<string, () => ReactNode> = {
        image: () => image,
        video: () => <video controls src={mediaUrl} />,
    };

    const className = clsx({
        'has-media-on-the-right': 'right' === mediaPosition,
        'is-stacked-on-mobile': isStackedOnMobile,
        [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        'is-image-fill-element': imageFill,
    });

    let gridTemplateColumns: string | undefined;
    if (mediaWidth !== DEFAULT_MEDIA_WIDTH) {
        gridTemplateColumns =
            'right' === mediaPosition
                ? `auto ${mediaWidth}%`
                : `${mediaWidth}% auto`;
    }
    const style: CSSProperties = {
        gridTemplateColumns,
    };

    if ('right' === mediaPosition) {
        return (
            <div {...useBlockProps.save({ className, style })}>
                <div
                    {...useInnerBlocksProps.save({
                        className: 'wp-block-media-text__content',
                    })}
                />
                <figure className="wp-block-media-text__media">
                    {(mediaTypeRenders[mediaType ?? ''] || noop)()}
                </figure>
            </div>
        );
    }
    return (
        <div {...useBlockProps.save({ className, style })}>
            <figure className="wp-block-media-text__media">
                {(mediaTypeRenders[mediaType ?? ''] || noop)()}
            </figure>
            <div
                {...useInnerBlocksProps.save({
                    className: 'wp-block-media-text__content',
                })}
            />
        </div>
    );
}
