/**
 * Post-context core block renderers: post-title, post-content, post-excerpt,
 * post-date, post-author, post-featured-image. Each block reads the actual
 * post data from `_resolved*` attributes that a host-side resolver stamps
 * onto the block tree before rendering — the renderer itself never reaches
 * out to a data store, so it stays pure and identical to the Blade and React
 * counterparts.
 */

import { defineComponent, h } from 'vue';
import type { VNode } from 'vue';
import {
    attrBoolean,
    attrInt,
    attrRecord,
    attrString,
    classList,
    layoutClass,
} from '../../support/attributes';
import { compileBlockSupports, type CompiledBlockSupports } from '../../support/blockSupports';
import { layoutWrapperClasses } from '../../support/layoutContainer';
import { safeCssValue } from '../../support/cssValue';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps, withStyle } from '../shared';

function buildLinkProps(
    href: string,
    linkTarget: string,
    rel: string,
    linkClass = ''
): Record<string, string> {
    const props: Record<string, string> = { href };

    if (linkClass !== '') {
        props.class = linkClass;
    }

    if (linkTarget === '_blank') {
        props.target = '_blank';
        props.rel = `noopener noreferrer${rel === '' ? '' : ` ${rel}`}`.trim();
    } else if (rel !== '') {
        props.rel = rel;
    }

    return props;
}

export const PostTitleBlock = defineComponent({
    name: 'PostTitleBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const rawLevel = attrInt(props.attributes.level, 2);
            const align = attrString(props.attributes.textAlign);
            const isLink = attrBoolean(props.attributes.isLink);
            const linkTarget = attrString(props.attributes.linkTarget);
            const rel = attrString(props.attributes.rel);
            const linkClass = attrString(props.attributes.linkClass);
            const className = attrString(props.attributes.className);

            const title = attrString(props.attributes._resolvedTitle);
            const permalink = safeUrl(props.attributes._resolvedPermalink);

            // Level 0 is the "Paragraph" option of the heading-level dropdown (#994).
            const tag = rawLevel === 0 ? 'p' : `h${Math.max(1, Math.min(6, rawLevel))}`;

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-title',
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            if (isLink && permalink !== '') {
                const linkProps = buildLinkProps(permalink, linkTarget, rel, linkClass);

                return h(tag, withStyle({ class: classes }, supports.style), [h('a', { ...linkProps, innerHTML: title })]);
            }

            return h(tag, { ...withStyle({ class: classes }, supports.style), innerHTML: title });
        };
    },
});

export const PostContentBlock = defineComponent({
    name: 'PostContentBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);
            const content = attrString(props.attributes._resolvedContent);

            // #702 — the block stores a `layout` attribute like every other
            // layout-supporting block, but the wrapper never surfaced it.
            // Without `is-layout-constrained` here the content-size
            // containment rules the theme-token compiler emits for
            // `.wp-block-post-content` can never match.
            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'entry-content',
                'wp-block-post-content',
                // Per-instance layout scope stamped by `stampLayoutScopes()`
                // (#871), then the layout modifier classes (F-3).
                ...layoutWrapperClasses(props.attributes, 'post-content', layoutClass(props.attributes)),
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            return h('div', { ...withStyle({ class: classes }, supports.style), innerHTML: content });
        };
    },
});

export const PostExcerptBlock = defineComponent({
    name: 'PostExcerptBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const moreText = attrString(props.attributes.moreText);
            const showMoreOnNewLine = attrBoolean(props.attributes.showMoreOnNewLine, true);
            const className = attrString(props.attributes.className);

            const excerpt = attrString(props.attributes._resolvedExcerpt);
            const permalink = safeUrl(props.attributes._resolvedPermalink);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-excerpt',
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            const moreNode: VNode | null = (() => {
                if (moreText === '') {
                    return null;
                }

                if (permalink !== '') {
                    return h(
                        'a',
                        { class: 'wp-block-post-excerpt__more-link', href: permalink },
                        moreText
                    );
                }

                return h('span', { class: 'wp-block-post-excerpt__more-text' }, moreText);
            })();

            const excerptParaChildren: Array<VNode | string> = [
                h('span', { innerHTML: excerpt }),
            ];

            if (moreNode !== null && !showMoreOnNewLine) {
                excerptParaChildren.push(' ', moreNode);
            }

            const children: VNode[] = [
                h('p', { class: 'wp-block-post-excerpt__excerpt' }, excerptParaChildren),
            ];

            if (moreNode !== null && showMoreOnNewLine) {
                children.push(h('p', { class: 'wp-block-post-excerpt__more-text' }, [moreNode]));
            }

            return h('div', withStyle({ class: classes }, supports.style), children);
        };
    },
});

export const PostDateBlock = defineComponent({
    name: 'PostDateBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const displayType =
                attrString(props.attributes.displayType, 'date') === 'modified'
                    ? 'modified'
                    : 'date';
            const isLink = attrBoolean(props.attributes.isLink);
            const className = attrString(props.attributes.className);

            const datetime =
                displayType === 'modified'
                    ? attrString(props.attributes._resolvedModifiedDate)
                    : attrString(props.attributes._resolvedDate);
            const formatted =
                displayType === 'modified'
                    ? attrString(props.attributes._resolvedModifiedDateFormatted, datetime)
                    : attrString(props.attributes._resolvedDateFormatted, datetime);
            const permalink = safeUrl(props.attributes._resolvedPermalink);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-date',
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            const timeProps: Record<string, string> = {};

            if (datetime !== '') {
                timeProps.datetime = datetime;
            }

            const timeNode = h('time', timeProps, formatted);

            const inner =
                isLink && permalink !== '' ? h('a', { href: permalink }, [timeNode]) : timeNode;

            return h('div', withStyle({ class: classes }, supports.style), [inner]);
        };
    },
});

export const PostAuthorBlock = defineComponent({
    name: 'PostAuthorBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const showAvatar = attrBoolean(props.attributes.showAvatar, true);
            const showBio = attrBoolean(props.attributes.showBio);
            const avatarSize = Math.max(1, attrInt(props.attributes.avatarSize, 24));
            const byline = attrString(props.attributes.byline);
            const isLink = attrBoolean(props.attributes.isLink);
            const className = attrString(props.attributes.className);

            const name = attrString(props.attributes._resolvedAuthorName);
            const bio = attrString(props.attributes._resolvedAuthorBio);
            const authorUrl = safeUrl(props.attributes._resolvedAuthorUrl);
            const avatarUrl = safeUrl(props.attributes._resolvedAuthorAvatar);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-author',
                // Duotone scope stamped by `stampDuotoneScopes()`.
                attrString(props.attributes._veDuotone) || null,
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            const children: VNode[] = [];

            if (showAvatar && avatarUrl !== '') {
                children.push(
                    h('div', { class: 'wp-block-post-author__avatar' }, [
                        h('img', {
                            alt: name,
                            width: avatarSize,
                            height: avatarSize,
                            src: avatarUrl,
                        }),
                    ])
                );
            }

            const contentChildren: VNode[] = [];

            if (byline !== '') {
                contentChildren.push(
                    h('p', { class: 'wp-block-post-author__byline' }, byline)
                );
            }

            const nameNode =
                isLink && authorUrl !== '' ? h('a', { href: authorUrl }, name) : name;

            contentChildren.push(
                h('p', { class: 'wp-block-post-author__name' }, [nameNode])
            );

            if (showBio && bio !== '') {
                contentChildren.push(
                    h('p', { class: 'wp-block-post-author__bio', innerHTML: bio })
                );
            }

            children.push(h('div', { class: 'wp-block-post-author__content' }, contentChildren));

            return h('div', withStyle({ class: classes }, supports.style), children);
        };
    },
});

export const PostAuthorNameBlock = defineComponent({
    name: 'PostAuthorNameBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const isLink = attrBoolean(props.attributes.isLink);
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const className = attrString(props.attributes.className);

            const name = attrString(props.attributes._resolvedAuthorName);
            const authorUrl = safeUrl(props.attributes._resolvedAuthorUrl);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-author-name',
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            if (isLink && authorUrl !== '') {
                const linkProps = buildLinkProps(authorUrl, linkTarget, '');

                return h('div', withStyle({ class: classes }, supports.style), [h('a', linkProps, name)]);
            }

            return h('div', withStyle({ class: classes }, supports.style), name);
        };
    },
});

export const PostAuthorBiographyBlock = defineComponent({
    name: 'PostAuthorBiographyBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);
            const bio = attrString(props.attributes._resolvedAuthorBio);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-author-biography',
                align !== '' ? `has-text-align-${align}` : null,
                ...supports.classNames,
                className,
            ]);

            return h('p', { ...withStyle({ class: classes }, supports.style), innerHTML: bio });
        };
    },
});

/**
 * The Avatar's supports split as `avatar.blade.php` splits them (#952,
 * #920): block.json skips border serialization on the wrapper, so the
 * border is painted on the `<img>` (Blade's `BlockSupports::borderAttrs()`)
 * and every other support lands on the wrapper.
 */
function avatarSupports(attributes: Record<string, unknown>): {
    wrapper: CompiledBlockSupports;
    image: CompiledBlockSupports;
} {
    const style = attrRecord(attributes.style);

    return {
        wrapper: compileBlockSupports({ ...attributes, borderColor: undefined, style: { ...style, border: undefined } }),
        image: compileBlockSupports({ borderColor: attributes.borderColor, style: { border: style.border } }),
    };
}

export const AvatarBlock = defineComponent({
    name: 'AvatarBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const size = Math.max(1, attrInt(props.attributes.size, 96));
            const isLink = attrBoolean(props.attributes.isLink);
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const className = attrString(props.attributes.className);

            const avatarUrl = safeUrl(props.attributes._resolvedAuthorAvatar);
            const alt = attrString(props.attributes._resolvedAuthorName);
            const authorUrl = safeUrl(props.attributes._resolvedAuthorUrl);

            const supports = avatarSupports(props.attributes);
            // Duotone scope stamped by `stampDuotoneScopes()`.
            const classes = classList([
                'wp-block-avatar',
                attrString(props.attributes._veDuotone) || null,
                ...supports.wrapper.classNames,
                className,
            ]);
            const wrapperProps = withStyle({ class: classes }, supports.wrapper.style);

            if (avatarUrl === '') {
                return h('div', wrapperProps);
            }

            const img = h(
                'img',
                withStyle(
                    {
                        alt,
                        width: size,
                        height: size,
                        src: avatarUrl,
                        class: classList(['avatar', 'wp-block-avatar__image', ...supports.image.classNames]),
                    },
                    supports.image.style
                )
            );

            if (isLink && authorUrl !== '') {
                const linkProps = buildLinkProps(authorUrl, linkTarget, '');

                return h('div', wrapperProps, [h('a', linkProps, [img])]);
            }

            return h('div', wrapperProps, [img]);
        };
    },
});

export const PostFeaturedImageBlock = defineComponent({
    name: 'PostFeaturedImageBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const isLink = attrBoolean(props.attributes.isLink);
            const linkTarget = attrString(props.attributes.linkTarget);
            const rel = attrString(props.attributes.rel);
            const aspect = attrString(props.attributes.aspectRatio);
            const scale = attrString(props.attributes.scale);
            const frameWidth = safeCssValue(attrString(props.attributes.width)) ?? '';
            const frameHeight = safeCssValue(attrString(props.attributes.height)) ?? '';
            const sizeSlug = attrString(props.attributes.sizeSlug);
            const className = attrString(props.attributes.className);

            const imageUrl = safeUrl(props.attributes._resolvedImageUrl);
            const alt = attrString(props.attributes._resolvedImageAlt);
            const width = Math.max(0, attrInt(props.attributes._resolvedImageWidth));
            const height = Math.max(0, attrInt(props.attributes._resolvedImageHeight));
            const permalink = safeUrl(props.attributes._resolvedPermalink);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-post-featured-image',
                sizeSlug !== '' ? `size-${sizeSlug}` : null,
                // Duotone scope stamped by `stampDuotoneScopes()`.
                attrString(props.attributes._veDuotone) || null,
                ...supports.classNames,
                className,
            ]);

            if (imageUrl === '') {
                return h('figure', withStyle({ class: classes }, supports.style));
            }

            // The supports (#920), then the frame sizing, which only
            // applies once there is an image to frame.
            const figureStyle: Record<string, string> = { ...supports.style };

            if (frameWidth !== '') {
                figureStyle.width = frameWidth;
            }

            if (frameHeight !== '') {
                figureStyle.height = frameHeight;
            }

            const figureProps = withStyle({}, figureStyle);

            const style: Record<string, string> = {};

            if (aspect !== '') {
                style.aspectRatio = aspect;
            }

            if (frameHeight !== '') {
                style.height = frameHeight;
            }

            // #948 — a fixed frame crops with `scale`, defaulting to block.json's
            // `cover` (never saved), as the Blade renderer does.
            if (scale !== '') {
                style.objectFit = scale;
            } else if (aspect !== '' || frameHeight !== '') {
                style.objectFit = 'cover';
            }

            const imgProps: Record<string, unknown> = { src: imageUrl, alt };

            if (Object.keys(style).length > 0) {
                imgProps.style = style;
            }

            if (width > 0) {
                imgProps.width = width;
            }

            if (height > 0) {
                imgProps.height = height;
            }

            const img = h('img', imgProps);

            if (isLink && permalink !== '') {
                const linkProps = buildLinkProps(permalink, linkTarget, rel);

                return h('figure', { class: classes, ...figureProps }, [h('a', linkProps, [img])]);
            }

            return h('figure', { class: classes, ...figureProps }, [img]);
        };
    },
});
