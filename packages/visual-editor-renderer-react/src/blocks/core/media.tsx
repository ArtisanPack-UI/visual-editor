/**
 * Media-family core block renderers: image, gallery, video, audio, file,
 * embed. Each matches the HTML shape of the corresponding Blade partial so
 * rendered output is interchangeable with the server-side renderer.
 */

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { styleProp } from '../../support/styleProp';
import { safeCssValue } from '../../support/cssValue';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

/**
 * Object-fit keywords the image block's Scale control writes (#873).
 */
const IMAGE_SCALES = ['cover', 'contain', 'fill', 'none', 'scale-down'];

/**
 * Unitless numeric string — a legacy image dimension (#985).
 */
const LEGACY_DIMENSION = /^\d+(\.\d+)?$/;

/**
 * The CSS lengths (plus `auto`) an image `width` / `height` may carry (#985).
 */
const CSS_DIMENSION =
    /^(auto|(\d+(\.\d+)?|\.\d+)(px|em|rem|%|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|ch|ex|cm|mm|in|pt|pc))$/;

/**
 * Resolve an image `width` / `height` attribute (#985), mirroring
 * `image.blade.php`: a legacy numeric value (a number or a unitless
 * numeric string) keeps the integer HTML attribute and nothing else; a
 * CSS length or `auto` becomes an inline style, plus the HTML attribute
 * for a whole-px value; anything else is dropped.
 */
function imageDimension(value: unknown): { attr?: number; style?: string } {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return { attr: Math.trunc(value) };
    }

    if (typeof value !== 'string') {
        return {};
    }

    if (LEGACY_DIMENSION.test(value)) {
        return { attr: Number.parseInt(value, 10) };
    }

    if (!CSS_DIMENSION.test(value)) {
        return {};
    }

    const px = /^(\d+)px$/.exec(value);

    return px === null ? { style: value } : { attr: Number.parseInt(px[1], 10), style: value };
}

/**
 * The `<img>` style for the image block's `aspectRatio` + `scale`
 * attributes (#873), mirroring `image.blade.php`: the ratio must pass
 * `safeCssValue`, the scale must be a known object-fit keyword. The
 * resolved width / height (#985) follow, in the editor `save()` order.
 */
function imageStyle(
    attributes: Record<string, unknown>,
    width: string | undefined,
    height: string | undefined,
): Record<string, string> | undefined {
    const style: Record<string, string> = {};
    const aspectRatio =
        typeof attributes.aspectRatio === 'string' ? safeCssValue(attributes.aspectRatio) : null;
    const scale = attributes.scale;

    if (aspectRatio !== null) {
        style.aspectRatio = aspectRatio.trim();
    }

    if (typeof scale === 'string' && IMAGE_SCALES.includes(scale)) {
        style.objectFit = scale;
    }

    if (width !== undefined) {
        style.width = width;
    }

    if (height !== undefined) {
        style.height = height;
    }

    return Object.keys(style).length > 0 ? style : undefined;
}

export function ImageBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attributes.url);
    const alt = attrString(attributes.alt);
    const caption = attrString(attributes.caption);
    const href = safeUrl(attributes.href);
    const id = attrInt(attributes.id);
    const width = imageDimension(attributes.width);
    const height = imageDimension(attributes.height);

    const align = attrString(attributes.align);
    const sizeSlug = attrString(attributes.sizeSlug);
    const className = attrString(attributes.className);

    const supports = compileBlockSupports(attributes);
    // Blade order: base, size, is-resized (#985), duotone scope (stamped by
    // `stampDuotoneScopes()`), then the supports' align, the other
    // supports (#920) and className.
    const figureClasses = classList([
        'wp-block-image',
        sizeSlug !== '' ? `size-${sizeSlug}` : null,
        width.style !== undefined || height.style !== undefined ? 'is-resized' : null,
        attrString(attributes._veDuotone) || null,
        align !== '' ? `align${align}` : null,
        ...supports.classNames,
        className,
    ]);

    const imgClass = id > 0 ? `wp-image-${id}` : undefined;
    const img =
        url === '' ? null : (
            <img
                src={url}
                alt={alt}
                className={imgClass}
                width={width.attr}
                height={height.attr}
                style={imageStyle(attributes, width.style, height.style)}
            />
        );

    return (
        <figure className={figureClasses} style={styleProp(supports.style)}>
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

    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-gallery',
        'has-nested-images',
        columns > 0 ? `columns-${columns}` : null,
        imageCrop ? 'is-cropped' : null,
        ...supports.classNames,
        className,
    ]);

    return (
        <figure className={classes} style={styleProp(supports.style)}>
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

    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-video',
        align !== '' ? `align${align}` : null,
        ...supports.classNames,
        className,
    ]);

    return (
        <figure className={classes} style={styleProp(supports.style)}>
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

    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-audio',
        align !== '' ? `align${align}` : null,
        ...supports.classNames,
        className,
    ]);

    return (
        <figure className={classes} style={styleProp(supports.style)}>
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

    const supports = compileBlockSupports(attributes);
    const classes = classList(['wp-block-file', ...supports.classNames, className]);
    const style = styleProp(supports.style);
    const linkLabel = fileName !== '' ? fileName : href;
    // Mirrors the editor save: the button points at the file-name link
    // for its accessible description, but only when that link has a name.
    const fileId = attrString(attributes.fileId);
    const describedById = fileName !== '' && fileId !== '' ? fileId : undefined;

    if (href === '') {
        return <div className={classes} style={style} />;
    }

    return (
        <div className={classes} style={style}>
            <a id={describedById} href={textLinkHref !== '' ? textLinkHref : href}>
                {linkLabel}
            </a>
            {showDownload ? (
                <a
                    href={href}
                    className="wp-block-file__button wp-element-button"
                    download
                    aria-describedby={describedById}
                >
                    {download}
                </a>
            ) : null}
        </div>
    );
}

/**
 * Reduce an embed provider slug the way `embed.blade.php` does
 * (`preg_replace( '/[^a-z0-9\-]/i', '-', … )`): case is kept, and every
 * other character becomes one `-` per UTF-8 byte, since the PHP pattern
 * has no `/u` flag.
 */
function embedProviderSlug(provider: string): string {
    return provider.replace(/[^a-zA-Z0-9-]/gu, (char) => {
        const codePoint = char.codePointAt(0) ?? 0;
        const bytes = codePoint < 0x80 ? 1 : codePoint < 0x800 ? 2 : codePoint < 0x10000 ? 3 : 4;

        return '-'.repeat(bytes);
    });
}

export function EmbedBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = attrString(attributes.url);
    const provider = attrString(attributes.providerNameSlug);
    const caption = attrString(attributes.caption);
    const ratio = attrString(attributes.aspectRatio);
    const type = attrString(attributes.type);

    const sanitizedProvider = embedProviderSlug(provider);
    const supports = compileBlockSupports(attributes);

    const classes = classList([
        'wp-block-embed',
        sanitizedProvider !== '' ? `is-provider-${sanitizedProvider}` : null,
        sanitizedProvider !== '' ? `wp-block-embed-${sanitizedProvider}` : null,
        // PHP's `empty()` also skips `'0'`.
        type !== '' && type !== '0' ? `is-type-${type}` : null,
        ratio !== '' ? `wp-embed-aspect-${ratio.replace(/\//g, '-')}` : null,
        ratio !== '' ? 'wp-has-aspect-ratio' : null,
        ...supports.classNames,
        // `className` carries the `ve-r-*` / `ve-fx-*` scopes too (M-7).
        attrString(attributes.className),
    ]);

    return (
        <figure className={classes} style={styleProp(supports.style)}>
            <div className="wp-block-embed__wrapper">{url === '' ? null : url}</div>
            {caption.trim() !== '' ? (
                <figcaption dangerouslySetInnerHTML={{ __html: caption }} />
            ) : null}
        </figure>
    );
}
