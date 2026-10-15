/**
 * Media-family core block renderers: image, gallery, video, audio, file,
 * embed. Each matches the HTML shape of the corresponding Blade partial so
 * rendered output is interchangeable with the server-side renderer.
 */

import { defineComponent, h } from 'vue';
import type { VNode } from 'vue';
import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeCssValue } from '../../support/cssValue';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

/**
 * Object-fit keywords the image block's Scale control writes (#873).
 */
const IMAGE_SCALES = ['cover', 'contain', 'fill', 'none', 'scale-down'];

/**
 * The `<img>` style for the image block's `aspectRatio` + `scale`
 * attributes (#873), mirroring `image.blade.php`: the ratio must pass
 * `safeCssValue`, the scale must be a known object-fit keyword.
 */
function imageStyle(attributes: Record<string, unknown>): Record<string, string> | undefined {
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

    return Object.keys(style).length > 0 ? style : undefined;
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
            const hasDimension = (key: 'width' | 'height'): boolean =>
                props.attributes[key] !== undefined &&
                props.attributes[key] !== null &&
                props.attributes[key] !== '';
            const width = hasDimension('width') ? attrInt(props.attributes.width) : undefined;
            const height = hasDimension('height') ? attrInt(props.attributes.height) : undefined;

            const align = attrString(props.attributes.align);
            const sizeSlug = attrString(props.attributes.sizeSlug);
            const className = attrString(props.attributes.className);

            // Blade order: base, size, duotone scope (stamped by
            // `stampDuotoneScopes()`), then the supports' align and className.
            const figureClasses = classList([
                'wp-block-image',
                sizeSlug !== '' ? `size-${sizeSlug}` : null,
                attrString(props.attributes._veDuotone) || null,
                align !== '' ? `align${align}` : null,
                className,
            ]);

            const imgProps: Record<string, unknown> = { src: url, alt };

            if (id > 0) {
                imgProps.class = `wp-image-${id}`;
            }

            if (width !== undefined) {
                imgProps.width = width;
            }

            if (height !== undefined) {
                imgProps.height = height;
            }

            const style = imageStyle(props.attributes);

            if (style !== undefined) {
                imgProps.style = style;
            }

            const img: VNode | null = url === '' ? null : h('img', imgProps);

            const children: VNode[] = [];

            if (img !== null) {
                children.push(href !== '' ? h('a', { href }, [img]) : img);
            }

            if (caption.trim() !== '') {
                children.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', { class: figureClasses }, children);
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

            const classes = classList([
                'wp-block-gallery',
                'has-nested-images',
                columns > 0 ? `columns-${columns}` : null,
                imageCrop ? 'is-cropped' : null,
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

            return h('figure', { class: classes }, children);
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

            const classes = classList([
                'wp-block-video',
                align !== '' ? `align${align}` : null,
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

            return h('figure', { class: classes }, children);
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

            const classes = classList([
                'wp-block-audio',
                align !== '' ? `align${align}` : null,
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

            return h('figure', { class: classes }, children);
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

            const classes = classList(['wp-block-file', className]);
            const linkLabel = fileName !== '' ? fileName : href;
            // Mirrors the editor save: the button points at the file-name
            // link for its accessible description, but only when that link
            // has a name.
            const fileId = attrString(props.attributes.fileId);
            const describedById = fileName !== '' && fileId !== '' ? fileId : undefined;

            if (href === '') {
                return h('div', { class: classes });
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

            return h('div', { class: classes }, children);
        };
    },
});

export const EmbedBlock = defineComponent({
    name: 'EmbedBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = attrString(props.attributes.url);
            const provider = attrString(props.attributes.providerNameSlug).toLowerCase();
            const caption = attrString(props.attributes.caption);
            const ratio = attrString(props.attributes.aspectRatio);
            const type = attrString(props.attributes.type);

            const sanitizedProvider = provider.replace(/[^a-z0-9-]/g, '-');

            const classes = classList([
                'wp-block-embed',
                sanitizedProvider !== '' ? `is-provider-${sanitizedProvider}` : null,
                sanitizedProvider !== '' ? `wp-block-embed-${sanitizedProvider}` : null,
                type !== '' ? `is-type-${type}` : null,
                ratio !== '' ? `wp-embed-aspect-${ratio.replace(/\//g, '-')}` : null,
                ratio !== '' ? 'wp-has-aspect-ratio' : null,
            ]);

            const children: VNode[] = [
                h('div', { class: 'wp-block-embed__wrapper' }, url === '' ? undefined : url),
            ];

            if (caption.trim() !== '') {
                children.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', { class: classes }, children);
        };
    },
});
