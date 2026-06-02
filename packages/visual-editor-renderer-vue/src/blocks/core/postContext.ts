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
import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

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
            const level = Math.max(1, Math.min(6, rawLevel));
            const align = attrString(props.attributes.textAlign);
            const isLink = attrBoolean(props.attributes.isLink);
            const linkTarget = attrString(props.attributes.linkTarget);
            const rel = attrString(props.attributes.rel);
            const linkClass = attrString(props.attributes.linkClass);
            const className = attrString(props.attributes.className);

            const title = attrString(props.attributes._resolvedTitle);
            const permalink = safeUrl(props.attributes._resolvedPermalink);

            const tag = `h${level}`;

            const classes = classList([
                'wp-block-post-title',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);

            if (isLink && permalink !== '') {
                const linkProps = buildLinkProps(permalink, linkTarget, rel, linkClass);

                return h(tag, { class: classes }, [h('a', { ...linkProps, innerHTML: title })]);
            }

            return h(tag, { class: classes, innerHTML: title });
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

            const classes = classList([
                'entry-content',
                'wp-block-post-content',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);

            return h('div', { class: classes, innerHTML: content });
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

            const classes = classList([
                'wp-block-post-excerpt',
                align !== '' ? `has-text-align-${align}` : null,
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

            return h('div', { class: classes }, children);
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

            const classes = classList([
                'wp-block-post-date',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);

            const timeProps: Record<string, string> = {};

            if (datetime !== '') {
                timeProps.datetime = datetime;
            }

            const timeNode = h('time', timeProps, formatted);

            const inner =
                isLink && permalink !== '' ? h('a', { href: permalink }, [timeNode]) : timeNode;

            return h('div', { class: classes }, [inner]);
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

            const classes = classList([
                'wp-block-post-author',
                align !== '' ? `has-text-align-${align}` : null,
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

            return h('div', { class: classes }, children);
        };
    },
});

export const PostAuthorNameBlock = defineComponent({
    name: 'PostAuthorNameBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const isLink = attrBoolean(props.attributes.isLink);
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const className = attrString(props.attributes.className);

            const name = attrString(props.attributes._resolvedAuthorName);
            const authorUrl = safeUrl(props.attributes._resolvedAuthorUrl);

            const classes = classList(['wp-block-post-author-name', className]);

            if (isLink && authorUrl !== '') {
                const linkProps = buildLinkProps(authorUrl, linkTarget, '');

                return h('div', { class: classes }, [h('a', linkProps, name)]);
            }

            return h('div', { class: classes }, name);
        };
    },
});

export const PostAuthorBiographyBlock = defineComponent({
    name: 'PostAuthorBiographyBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const className = attrString(props.attributes.className);
            const bio = attrString(props.attributes._resolvedAuthorBio);

            const classes = classList(['wp-block-post-author-biography', className]);

            return h('p', { class: classes, innerHTML: bio });
        };
    },
});

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

            const classes = classList(['wp-block-avatar', className]);

            if (avatarUrl === '') {
                return h('div', { class: classes });
            }

            const img = h('img', {
                alt,
                width: size,
                height: size,
                src: avatarUrl,
            });

            if (isLink && authorUrl !== '') {
                const linkProps = buildLinkProps(authorUrl, linkTarget, '');

                return h('div', { class: classes }, [h('a', linkProps, [img])]);
            }

            return h('div', { class: classes }, [img]);
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
            const sizeSlug = attrString(props.attributes.sizeSlug);
            const className = attrString(props.attributes.className);

            const imageUrl = safeUrl(props.attributes._resolvedImageUrl);
            const alt = attrString(props.attributes._resolvedImageAlt);
            const width = Math.max(0, attrInt(props.attributes._resolvedImageWidth));
            const height = Math.max(0, attrInt(props.attributes._resolvedImageHeight));
            const permalink = safeUrl(props.attributes._resolvedPermalink);

            const classes = classList([
                'wp-block-post-featured-image',
                sizeSlug !== '' ? `size-${sizeSlug}` : null,
                className,
            ]);

            if (imageUrl === '') {
                return h('figure', { class: classes });
            }

            const style: Record<string, string> = {};

            if (aspect !== '') {
                style.aspectRatio = aspect;
            }

            if (scale !== '') {
                style.objectFit = scale;
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

                return h('figure', { class: classes }, [h('a', linkProps, [img])]);
            }

            return h('figure', { class: classes }, [img]);
        };
    },
});
