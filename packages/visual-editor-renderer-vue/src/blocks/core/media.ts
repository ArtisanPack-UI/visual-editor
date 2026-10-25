/**
 * Media-family core block renderers: image, gallery, video, audio, file,
 * embed. Each matches the HTML shape of the corresponding Blade partial so
 * rendered output is interchangeable with the server-side renderer.
 */

import { defineComponent, h } from 'vue';
import type { VNode } from 'vue';
import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { safeCssValue } from '../../support/cssValue';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps, withStyle } from '../shared';

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

/**
 * The image link's class / target / rel (#1022), mirroring
 * `core/image.blade.php`: a new-tab link always carries noopener +
 * noreferrer.
 */
function imageLinkAttrs(attributes: Record<string, unknown>): {
    className: string | undefined;
    target: string | undefined;
    rel: string | undefined;
} {
    const linkClass = attrString(attributes.linkClass).trim();
    const linkTarget = attrString(attributes.linkTarget);
    let rel = attrString(attributes.rel).trim();

    if (linkTarget === '_blank') {
        const tokens = rel.split(/\s+/).filter((t) => t !== '');

        for (const required of ['noopener', 'noreferrer']) {
            if (!tokens.includes(required)) {
                tokens.push(required);
            }
        }

        rel = tokens.join(' ');
    }

    return {
        className: linkClass === '' ? undefined : linkClass,
        target: linkTarget === '' ? undefined : linkTarget,
        rel: rel === '' ? undefined : rel,
    };
}

export const ImageBlock = defineComponent({
    name: 'ImageBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes.url);
            const alt = attrString(props.attributes.alt);
            const caption = attrString(props.attributes.caption);
            const href = safeUrl(props.attributes.href);
            const id = attrInt(props.attributes.id);
            const width = imageDimension(props.attributes.width);
            const height = imageDimension(props.attributes.height);

            const align = attrString(props.attributes.align);
            const sizeSlug = attrString(props.attributes.sizeSlug);
            const className = attrString(props.attributes.className);

            const supports = compileBlockSupports(props.attributes);
            // Blade order: base, size, is-resized (#985), duotone scope (stamped by
            // `stampDuotoneScopes()`), then the supports' align, the other
            // supports (#920) and className.
            const figureClasses = classList([
                'wp-block-image',
                sizeSlug !== '' ? `size-${sizeSlug}` : null,
                width.style !== undefined || height.style !== undefined ? 'is-resized' : null,
                attrString(props.attributes._veDuotone) || null,
                align !== '' ? `align${align}` : null,
                ...supports.classNames,
                className,
            ]);

            const imgProps: Record<string, unknown> = { src: url, alt };

            if (id > 0) {
                imgProps.class = `wp-image-${id}`;
            }

            if (width.attr !== undefined) {
                imgProps.width = width.attr;
            }

            if (height.attr !== undefined) {
                imgProps.height = height.attr;
            }

            const style = imageStyle(props.attributes, width.style, height.style);

            if (style !== undefined) {
                imgProps.style = style;
            }

            const img: VNode | null = url === '' ? null : h('img', imgProps);

            const children: VNode[] = [];

            if (img !== null) {
                if (href !== '') {
                    const link = imageLinkAttrs(props.attributes);
                    // Vue renders `class: undefined` as `class=""`, so only
                    // set the keys that carry a value.
                    const linkProps: Record<string, unknown> = {};

                    if (link.className !== undefined) {
                        linkProps.class = link.className;
                    }

                    linkProps.href = href;

                    if (link.target !== undefined) {
                        linkProps.target = link.target;
                    }

                    if (link.rel !== undefined) {
                        linkProps.rel = link.rel;
                    }

                    children.push(h('a', linkProps, [img]));
                } else {
                    children.push(img);
                }
            }

            if (caption.trim() !== '') {
                children.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', withStyle({ class: figureClasses }, supports.style), children);
        };
    },
});

export const GalleryBlock = defineComponent({
    name: 'GalleryBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const columns = attrInt(props.attributes.columns);
            const imageCrop = attrBoolean(props.attributes.imageCrop);
            const className = attrString(props.attributes.className);
            const caption = attrString(props.attributes.caption);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-gallery',
                'has-nested-images',
                columns > 0 ? `columns-${columns}` : null,
                imageCrop ? 'is-cropped' : null,
                ...supports.classNames,
                className,
            ]);

            const children: VNode[] = [];
            const inner = slots.default ? slots.default() : [];

            children.push(...(inner as VNode[]));

            if (caption.trim() !== '') {
                children.push(
                    h('figcaption', {
                        class: 'blocks-gallery-caption',
                        innerHTML: caption,
                    })
                );
            }

            return h('figure', withStyle({ class: classes }, supports.style), children);
        };
    },
});

export const VideoBlock = defineComponent({
    name: 'VideoBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const src = safeUrl(props.attributes.src);
            const caption = attrString(props.attributes.caption);
            const align = attrString(props.attributes.align);
            const className = attrString(props.attributes.className);
            const poster = safeUrl(props.attributes.poster);
            const preload = attrString(props.attributes.preload, 'metadata');

            const controls =
                props.attributes.controls === undefined ? true : attrBoolean(props.attributes.controls);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-video',
                align !== '' ? `align${align}` : null,
                ...supports.classNames,
                className,
            ]);

            const children: VNode[] = [];

            if (src !== '') {
                children.push(
                    h('video', {
                        src,
                        preload,
                        controls,
                        autoplay: attrBoolean(props.attributes.autoplay),
                        loop: attrBoolean(props.attributes.loop),
                        muted: attrBoolean(props.attributes.muted),
                        playsinline: attrBoolean(props.attributes.playsInline),
                        poster: poster === '' ? undefined : poster,
                    })
                );
            }

            if (caption.trim() !== '') {
                children.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', withStyle({ class: classes }, supports.style), children);
        };
    },
});

export const AudioBlock = defineComponent({
    name: 'AudioBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const src = safeUrl(props.attributes.src);
            const caption = attrString(props.attributes.caption);
            const align = attrString(props.attributes.align);
            const className = attrString(props.attributes.className);
            const preload = attrString(props.attributes.preload, 'none');

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-audio',
                align !== '' ? `align${align}` : null,
                ...supports.classNames,
                className,
            ]);

            const children: VNode[] = [];

            if (src !== '') {
                children.push(
                    h('audio', {
                        controls: true,
                        src,
                        preload,
                        autoplay: attrBoolean(props.attributes.autoplay),
                        loop: attrBoolean(props.attributes.loop),
                    })
                );
            }

            if (caption.trim() !== '') {
                children.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', withStyle({ class: classes }, supports.style), children);
        };
    },
});

export const FileBlock = defineComponent({
    name: 'FileBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const href = safeUrl(props.attributes.href);
            const fileName = attrString(props.attributes.fileName);
            const textLinkHrefRaw = attrString(props.attributes.textLinkHref);
            const textLinkHref = safeUrl(textLinkHrefRaw === '' ? props.attributes.href : textLinkHrefRaw);
            const download = attrString(props.attributes.downloadButtonText, 'Download');
            const showDownload =
                props.attributes.showDownloadButton === undefined
                    ? true
                    : attrBoolean(props.attributes.showDownloadButton);
            const className = attrString(props.attributes.className);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList(['wp-block-file', ...supports.classNames, className]);
            const linkLabel = fileName !== '' ? fileName : href;
            // Mirrors the editor save: the button points at the file-name
            // link for its accessible description, but only when that link
            // has a name.
            const fileId = attrString(props.attributes.fileId);
            const describedById = fileName !== '' && fileId !== '' ? fileId : undefined;

            if (href === '') {
                return h('div', withStyle({ class: classes }, supports.style));
            }

            const children: VNode[] = [
                h('a', { id: describedById, href: textLinkHref !== '' ? textLinkHref : href }, linkLabel),
            ];

            if (showDownload) {
                children.push(
                    h(
                        'a',
                        {
                            href,
                            class: 'wp-block-file__button wp-element-button',
                            download: '',
                            'aria-describedby': describedById,
                        },
                        download
                    )
                );
            }

            return h('div', withStyle({ class: classes }, supports.style), children);
        };
    },
});

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

export const EmbedBlock = defineComponent({
    name: 'EmbedBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = attrString(props.attributes.url);
            const provider = attrString(props.attributes.providerNameSlug);
            const caption = attrString(props.attributes.caption);
            const ratio = attrString(props.attributes.aspectRatio);
            const type = attrString(props.attributes.type);

            const sanitizedProvider = embedProviderSlug(provider);

            const supports = compileBlockSupports(props.attributes);
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
                attrString(props.attributes.className),
            ]);

            const children: VNode[] = [
                h('div', { class: 'wp-block-embed__wrapper' }, url === '' ? undefined : url),
            ];

            if (caption.trim() !== '') {
                children.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', withStyle({ class: classes }, supports.style), children);
        };
    },
});
