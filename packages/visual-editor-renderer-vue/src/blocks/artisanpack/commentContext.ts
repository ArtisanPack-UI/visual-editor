/**
 * Comments-family renderers (#519) — Vue.
 *
 * 16 blocks mirroring the React + Blade implementations. Each leaf
 * reads `_resolved*` attributes stamped by visual-editor's
 * CommentResolver / PostResolver before the tree reaches the
 * renderer.
 */

import { defineComponent, getCurrentInstance, h } from 'vue';

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

// --- Pass 1: comments wrapper + per-comment cluster ---------------------

export const CommentsBlock = defineComponent({
    name: 'CommentsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const raw = attrString(props.attributes.tagName, 'div').toLowerCase();
            const tag = ['div', 'section', 'article', 'aside', 'footer'].includes(raw) ? raw : 'div';
            const className = attrString(props.attributes.className);
            return h(
                tag,
                { class: classList(['wp-block-comments', className]) },
                slots.default?.()
            );
        };
    },
});

export const CommentTemplateBlock = defineComponent({
    name: 'CommentTemplateBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            return h(
                'ol',
                { class: classList(['wp-block-comment-template', className]) },
                slots.default?.()
            );
        };
    },
});

export const CommentAuthorNameBlock = defineComponent({
    name: 'CommentAuthorNameBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const isLink = attrBoolean(props.attributes.isLink, true);
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const align = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);
            const name = attrString(props.attributes._resolvedAuthorName);
            const url = safeUrl(props.attributes._resolvedAuthorUrl);

            const classes = classList([
                'wp-block-comment-author-name',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);

            if (isLink && url !== '') {
                const linkProps: Record<string, string> = { href: url };
                if (linkTarget === '_blank') {
                    linkProps.target = '_blank';
                    linkProps.rel = 'noopener noreferrer';
                }
                return h('div', { class: classes }, [h('a', linkProps, name)]);
            }
            return h('div', { class: classes }, name);
        };
    },
});

export const CommentAuthorAvatarBlock = defineComponent({
    name: 'CommentAuthorAvatarBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedAvatarUrl);
            const alt = attrString(props.attributes._resolvedAvatarAlt);
            const width = Math.max(1, attrInt(props.attributes.width, 96));
            const height = Math.max(1, attrInt(props.attributes.height, 96));
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-comment-author-avatar', className]);

            if (url === '') {
                return h('div', { class: classes });
            }
            return h('div', { class: classes }, [
                h('img', { src: url, alt, width, height, loading: 'lazy' }),
            ]);
        };
    },
});

export const CommentContentBlock = defineComponent({
    name: 'CommentContentBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);
            const content = attrString(props.attributes._resolvedContent);
            const classes = classList([
                'wp-block-comment-content',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);
            return h('div', { class: classes, innerHTML: content });
        };
    },
});

export const CommentDateBlock = defineComponent({
    name: 'CommentDateBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const isLink = attrBoolean(props.attributes.isLink, true);
            const className = attrString(props.attributes.className);
            const datetime = attrString(props.attributes._resolvedDate);
            const formatted = attrString(props.attributes._resolvedDateFormatted, datetime);
            const url = safeUrl(props.attributes._resolvedPermalink);
            const classes = classList(['wp-block-comment-date', className]);

            const timeNode =
                datetime !== '' ? h('time', { datetime }, formatted) : formatted;

            const inner = isLink && url !== '' ? h('a', { href: url }, [timeNode]) : timeNode;
            return h('div', { class: classes }, [inner]);
        };
    },
});

export const CommentEditLinkBlock = defineComponent({
    name: 'CommentEditLinkBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedEditLinkUrl);
            const label = attrString(props.attributes._resolvedEditLinkLabel, 'Edit');
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const className = attrString(props.attributes.className);
            if (url === '') {
                return null;
            }
            const linkProps: Record<string, string> = { href: url };
            if (linkTarget === '_blank') {
                linkProps.target = '_blank';
                linkProps.rel = 'noopener noreferrer';
            }
            return h(
                'div',
                { class: classList(['wp-block-comment-edit-link', className]) },
                [h('a', linkProps, label)]
            );
        };
    },
});

export const CommentReplyLinkBlock = defineComponent({
    name: 'CommentReplyLinkBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedReplyLinkUrl);
            const label = attrString(props.attributes._resolvedReplyLinkLabel, 'Reply');
            const className = attrString(props.attributes.className);
            if (url === '') {
                return null;
            }
            return h(
                'div',
                { class: classList(['wp-block-comment-reply-link', className]) },
                [h('a', { href: url }, label)]
            );
        };
    },
});

// --- Pass 2: post-comments-* metadata + pagination cluster -------------

export const PostCommentsFormBlock = defineComponent({
    name: 'PostCommentsFormBlock',
    props: blockRendererProps,
    setup(props) {
        // Element IDs derive from the component instance's uid so
        // multiple post-comments-form blocks on the same page (e.g.
        // an archive listing) get unique label-for/input-id pairings.
        const uid = getCurrentInstance()?.uid ?? 0;
        const authorId = `ve-comment-author-${uid}`;
        const emailId = `ve-comment-email-${uid}`;
        const contentId = `ve-comment-content-${uid}`;

        return () => {
            const className = attrString(props.attributes.className);
            const classes = classList([
                'wp-block-post-comments-form',
                'comment-respond',
                className,
            ]);
            const postId = attrInt(props.attributes._resolvedPostId, 0);
            // Host apps redirect form submissions to their own form-handler
            // controller by stamping `_resolvedFormAction` on the block via
            // CommentInliner. The default points at cms-framework's REST
            // endpoint, which returns JSON — fine for SPAs, not for HTML
            // form submits where the user expects a redirect back to the
            // post.
            const formAction = safeUrl(
                attrString(props.attributes._resolvedFormAction, '/api/v1/comments')
            );

            const formChildren = [
                h('h3', { class: 'comment-reply-title' }, 'Leave a Comment'),
                h('form', { action: formAction, method: 'post', class: 'comment-form' }, [
                    postId > 0
                        ? h('input', { type: 'hidden', name: 'post_id', value: postId })
                        : null,
                    h('p', { class: 'comment-form-author' }, [
                        h('label', { for: authorId }, [
                            'Name ',
                            h('span', { 'aria-hidden': 'true' }, '*'),
                        ]),
                        h('input', { id: authorId, name: 'author_name', type: 'text', required: true }),
                    ]),
                    h('p', { class: 'comment-form-email' }, [
                        h('label', { for: emailId }, [
                            'Email ',
                            h('span', { 'aria-hidden': 'true' }, '*'),
                        ]),
                        h('input', { id: emailId, name: 'author_email', type: 'email', required: true }),
                    ]),
                    h('p', { class: 'comment-form-comment' }, [
                        h('label', { for: contentId }, [
                            'Comment ',
                            h('span', { 'aria-hidden': 'true' }, '*'),
                        ]),
                        h('textarea', { id: contentId, name: 'content', rows: 6, required: true }),
                    ]),
                    h('p', { class: 'form-submit' }, [
                        // Deliberately no `name: 'submit'` — that
                        // attribute shadows the form's `.submit`
                        // property in the DOM and breaks any JS that
                        // hooks the form.
                        h('input', {
                            type: 'submit',
                            class: 'submit',
                            value: 'Post Comment',
                        }),
                    ]),
                ]),
            ];

            return h('div', { class: classes }, formChildren);
        };
    },
});

export const PostCommentsCountBlock = defineComponent({
    name: 'PostCommentsCountBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const count = attrInt(props.attributes._resolvedCommentCount, 0);
            const className = attrString(props.attributes.className);
            return h(
                'div',
                { class: classList(['wp-block-post-comments-count', className]) },
                String(count)
            );
        };
    },
});

export const PostCommentsLinkBlock = defineComponent({
    name: 'PostCommentsLinkBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const count = attrInt(props.attributes._resolvedCommentCount, 0);
            const url = safeUrl(props.attributes._resolvedCommentsUrl);
            const fallback = count === 1 ? '1 Comment' : `${count} Comments`;
            const label = attrString(props.attributes._resolvedCommentsLabel, fallback);
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-post-comments-link', className]);
            if (url === '') {
                return h('div', { class: classes }, label);
            }
            return h('div', { class: classes }, [h('a', { href: url }, label)]);
        };
    },
});

export const PostCommentsTitleBlock = defineComponent({
    name: 'PostCommentsTitleBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const level = Math.max(1, Math.min(6, attrInt(props.attributes.level, 2)));
            const showCount = attrBoolean(props.attributes.showCommentsCount, true);
            const count = attrInt(props.attributes._resolvedCommentCount, 0);
            const fallback =
                count === 0 ? 'No Comments' : count === 1 ? '1 Comment' : `${count} Comments`;
            const title = showCount
                ? attrString(props.attributes._resolvedCommentsTitle, fallback)
                : 'Comments';
            const align = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);

            const tag = `h${level}`;
            const classes = classList([
                'wp-block-post-comments-title',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);
            return h(tag, { class: classes }, title);
        };
    },
});

export const CommentsPaginationBlock = defineComponent({
    name: 'CommentsPaginationBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            return h(
                'nav',
                {
                    class: classList(['wp-block-comments-pagination', className]),
                    'aria-label': 'Comments pagination',
                },
                slots.default?.()
            );
        };
    },
});

export const CommentsPaginationNextBlock = defineComponent({
    name: 'CommentsPaginationNextBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedNextPageUrl);
            const label = attrString(props.attributes.label, 'Newer Comments');
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-comments-pagination-next', className]);
            if (url === '') {
                return h('span', { class: classes }, label);
            }
            return h('a', { class: classes, href: url }, `${label} →`);
        };
    },
});

export const CommentsPaginationPreviousBlock = defineComponent({
    name: 'CommentsPaginationPreviousBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedPreviousPageUrl);
            const label = attrString(props.attributes.label, 'Older Comments');
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-comments-pagination-previous', className]);
            if (url === '') {
                return h('span', { class: classes }, label);
            }
            return h('a', { class: classes, href: url }, `← ${label}`);
        };
    },
});

interface ResolvedPage {
    readonly number?: unknown;
    readonly url?: unknown;
}

export const CommentsPaginationNumbersBlock = defineComponent({
    name: 'CommentsPaginationNumbersBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-comments-pagination-numbers', className]);
            const current = attrInt(props.attributes._resolvedCurrentPage, 1);
            const raw = props.attributes._resolvedPageNumbers;
            const pages: ResolvedPage[] = Array.isArray(raw) ? (raw as ResolvedPage[]) : [];

            const children = pages
                .map((page, idx) => {
                    const number =
                        typeof page.number === 'number' && Number.isFinite(page.number)
                            ? page.number
                            : 0;
                    if (number === 0) {
                        return null;
                    }
                    const href = safeUrl(typeof page.url === 'string' ? page.url : '');
                    const isCurrent = number === current;

                    // Only the actual current page gets aria-current="page".
                    // A non-current page with an empty href still renders
                    // as a plain span (no link target) but must not claim
                    // to be the current page.
                    if (isCurrent) {
                        return h(
                            'span',
                            {
                                key: `pg-${idx}-${number}`,
                                class: 'page-numbers current',
                                'aria-current': 'page',
                            },
                            String(number)
                        );
                    }
                    if (href === '') {
                        return h(
                            'span',
                            { key: `pg-${idx}-${number}`, class: 'page-numbers' },
                            String(number)
                        );
                    }
                    return h(
                        'a',
                        { key: `pg-${idx}-${number}`, class: 'page-numbers', href },
                        String(number)
                    );
                })
                .filter((child) => child !== null);

            return h('div', { class: classes }, children);
        };
    },
});
