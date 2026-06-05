/**
 * Media-family core block renderers: image, gallery, video, audio, file,
 * embed. Each matches the HTML shape of the corresponding Blade partial so
 * rendered output is interchangeable with the server-side renderer.
 */

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

export function ImageBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attributes.url);
    const alt = attrString(attributes.alt);
    const caption = attrString(attributes.caption);
    const href = safeUrl(attributes.href);
    const id = attrInt(attributes.id);
    const hasDimension = (key: 'width' | 'height'): boolean =>
        attributes[key] !== undefined && attributes[key] !== null && attributes[key] !== '';
    const width = hasDimension('width') ? attrInt(attributes.width) : undefined;
    const height = hasDimension('height') ? attrInt(attributes.height) : undefined;

    const align = attrString(attributes.align);
    const sizeSlug = attrString(attributes.sizeSlug);
    const className = attrString(attributes.className);

    const figureClasses = classList([
        'wp-block-image',
        align !== '' ? `align${align}` : null,
        sizeSlug !== '' ? `size-${sizeSlug}` : null,
        className,
    ]);

    const imgClass = id > 0 ? `wp-image-${id}` : undefined;
    const img =
        url === '' ? null : (
            <img
                src={url}
                alt={alt}
                className={imgClass}
                width={width}
                height={height}
            />
        );

    return (
        <figure className={figureClasses}>
            {img !== null && href !== '' ? <a href={href}>{img}</a> : img}
            {caption.trim() !== '' ? (
                <figcaption dangerouslySetInnerHTML={{ __html: caption }} />
            ) : null}
        </figure>
    );
}

export function GalleryBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const columns = attrInt(attributes.columns);
    const imageCrop = attrBoolean(attributes.imageCrop);
    const className = attrString(attributes.className);
    const caption = attrString(attributes.caption);

    const classes = classList([
        'wp-block-gallery',
        'has-nested-images',
        columns > 0 ? `columns-${columns}` : null,
        imageCrop ? 'is-cropped' : null,
        className,
    ]);

    return (
        <figure className={classes}>
            {children}
            {caption.trim() !== '' ? (
                <figcaption
                    className="blocks-gallery-caption"
                    dangerouslySetInnerHTML={{ __html: caption }}
                />
            ) : null}
        </figure>
    );
}

export function VideoBlock({ attributes }: BlockRendererProps): JSX.Element {
    const src = safeUrl(attributes.src);
    const caption = attrString(attributes.caption);
    const align = attrString(attributes.align);
    const className = attrString(attributes.className);
    const poster = safeUrl(attributes.poster);
    const preload = attrString(attributes.preload, 'metadata');

    const controls = attributes.controls === undefined ? true : attrBoolean(attributes.controls);

    const classes = classList([
        'wp-block-video',
        align !== '' ? `align${align}` : null,
        className,
    ]);

    return (
        <figure className={classes}>
            {src === '' ? null : (
                <video
                    src={src}
                    preload={preload}
                    controls={controls}
                    autoPlay={attrBoolean(attributes.autoplay)}
                    loop={attrBoolean(attributes.loop)}
                    muted={attrBoolean(attributes.muted)}
                    playsInline={attrBoolean(attributes.playsInline)}
                    poster={poster === '' ? undefined : poster}
                />
            )}
            {caption.trim() !== '' ? (
                <figcaption dangerouslySetInnerHTML={{ __html: caption }} />
            ) : null}
        </figure>
    );
}

export function AudioBlock({ attributes }: BlockRendererProps): JSX.Element {
    const src = safeUrl(attributes.src);
    const caption = attrString(attributes.caption);
    const align = attrString(attributes.align);
    const className = attrString(attributes.className);
    const preload = attrString(attributes.preload, 'none');

    const classes = classList([
        'wp-block-audio',
        align !== '' ? `align${align}` : null,
        className,
    ]);

    return (
        <figure className={classes}>
            {src === '' ? null : (
                <audio
                    controls
                    src={src}
                    preload={preload}
                    autoPlay={attrBoolean(attributes.autoplay)}
                    loop={attrBoolean(attributes.loop)}
                />
            )}
            {caption.trim() !== '' ? (
                <figcaption dangerouslySetInnerHTML={{ __html: caption }} />
            ) : null}
        </figure>
    );
}

export function FileBlock({ attributes }: BlockRendererProps): JSX.Element {
    const href = safeUrl(attributes.href);
    const fileName = attrString(attributes.fileName);
    const textLinkHrefRaw = attrString(attributes.textLinkHref);
    const textLinkHref = safeUrl(textLinkHrefRaw === '' ? attributes.href : textLinkHrefRaw);
    const download = attrString(attributes.downloadButtonText, 'Download');
    const showDownload =
        attributes.showDownloadButton === undefined ? true : attrBoolean(attributes.showDownloadButton);
    const className = attrString(attributes.className);

    const classes = classList(['wp-block-file', className]);
    const linkLabel = fileName !== '' ? fileName : href;

    if (href === '') {
        return <div className={classes} />;
    }

    return (
        <div className={classes}>
            <a href={textLinkHref !== '' ? textLinkHref : href}>{linkLabel}</a>
            {showDownload ? (
                <a href={href} className="wp-block-file__button" download>
                    {download}
                </a>
            ) : null}
        </div>
    );
}

export function EmbedBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = attrString(attributes.url);
    const provider = attrString(attributes.providerNameSlug).toLowerCase();
    const caption = attrString(attributes.caption);
    const ratio = attrString(attributes.aspectRatio);
    const type = attrString(attributes.type);

    const sanitizedProvider = provider.replace(/[^a-z0-9-]/g, '-');

    const classes = classList([
        'wp-block-embed',
        sanitizedProvider !== '' ? `is-provider-${sanitizedProvider}` : null,
        sanitizedProvider !== '' ? `wp-block-embed-${sanitizedProvider}` : null,
        type !== '' ? `is-type-${type}` : null,
        ratio !== '' ? `wp-embed-aspect-${ratio.replace(/\//g, '-')}` : null,
        ratio !== '' ? 'wp-has-aspect-ratio' : null,
    ]);

    return (
        <figure className={classes}>
            <div className="wp-block-embed__wrapper">{url === '' ? null : url}</div>
            {caption.trim() !== '' ? (
                <figcaption dangerouslySetInnerHTML={{ __html: caption }} />
            ) : null}
        </figure>
    );
}
