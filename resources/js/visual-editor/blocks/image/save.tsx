/**
 * Image — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/image/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/image`. Mixed documents containing
 * `core/image` and `artisanpack/image` render to identical HTML.
 */

import type { CSSProperties, ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    __experimentalGetElementClassName,
    __experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles,
    __experimentalGetShadowClassesAndStyles as getShadowClassesAndStyles,
} from '@wordpress/block-editor';

import { mediaPosition } from './utils';

interface ImageAttributes {
    readonly url?: string;
    readonly alt?: string;
    readonly caption?: string;
    readonly align?: string;
    readonly href?: string;
    readonly rel?: string;
    readonly linkClass?: string;
    readonly width?: string;
    readonly height?: string;
    readonly aspectRatio?: string;
    readonly scale?: string;
    readonly focalPoint?: { x?: number; y?: number };
    readonly id?: number;
    readonly linkTarget?: string;
    readonly sizeSlug?: string;
    readonly title?: string;
    readonly metadata?: {
        bindings?: Record<string, unknown> & {
            __default?: { source?: string };
            caption?: unknown;
        };
    };
    readonly [key: string]: unknown;
}

interface ImageSaveProps {
    readonly attributes: ImageAttributes;
}

interface BorderShadowProps {
    readonly className?: string;
    readonly style?: CSSProperties;
}

export default function save({ attributes }: ImageSaveProps): ReactElement {
    const {
        url,
        alt,
        caption,
        align,
        href,
        rel,
        linkClass,
        width,
        height,
        aspectRatio,
        scale,
        focalPoint,
        id,
        linkTarget,
        sizeSlug,
        title,
        metadata: { bindings = {} } = {},
    } = attributes;

    const newRel = !rel ? undefined : rel;
    const borderProps = getBorderClassesAndStyles(
        attributes as never
    ) as BorderShadowProps;
    const shadowProps = getShadowClassesAndStyles(
        attributes as never
    ) as BorderShadowProps;

    const classes = clsx({
        // All other align classes are handled by block supports.
        // `{ align: 'none' }` is unique to transforms for the image block.
        alignnone: 'none' === align,
        [`size-${sizeSlug}`]: sizeSlug,
        'is-resized': width || height,
        'has-custom-border':
            !!borderProps.className ||
            (borderProps.style &&
                Object.keys(borderProps.style).length > 0),
    });

    const imageClasses = clsx(borderProps.className, {
        [`wp-image-${id}`]: !!id,
    });

    const image = (
        <img
            src={url}
            alt={alt}
            className={imageClasses || undefined}
            style={{
                ...borderProps.style,
                ...shadowProps.style,
                aspectRatio,
                objectFit: scale,
                objectPosition:
                    focalPoint && scale
                        ? mediaPosition(focalPoint)
                        : undefined,
                width,
                height,
            }}
            title={title}
        />
    );

    const captionBindings = bindings as {
        caption?: unknown;
        __default?: { source?: string };
    };

    const displayCaption =
        !RichText.isEmpty(caption ?? '') ||
        captionBindings.caption ||
        captionBindings.__default?.source === 'core/pattern-overrides';

    const figure = (
        <>
            {href ? (
                <a
                    className={linkClass}
                    href={href}
                    target={linkTarget}
                    rel={newRel}
                >
                    {image}
                </a>
            ) : (
                image
            )}
            {displayCaption && (
                <RichText.Content
                    className={__experimentalGetElementClassName('caption')}
                    tagName="figcaption"
                    value={caption}
                />
            )}
        </>
    );

    return (
        <figure {...useBlockProps.save({ className: classes })}>
            {figure}
        </figure>
    );
}
