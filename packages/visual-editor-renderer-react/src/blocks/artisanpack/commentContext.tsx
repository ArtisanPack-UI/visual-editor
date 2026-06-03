/**
 * Comments-family renderers (#519).
 *
 * 16 blocks total — Pass 1 (comments wrapper + comment-template + 6
 * per-comment leaves) and Pass 2 (post-comments-* metadata +
 * pagination cluster). Each leaf reads its `_resolved*` attributes
 * stamped by visual-editor's CommentResolver / PostResolver before
 * the tree reaches the renderer, so this file stays pure — no data
 * fetching, no DOM side effects, byte-shape parity with the Blade
 * and Vue counterparts.
 */

import { useId } from 'react';
import type { JSX } from 'react';

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

// --- Pass 1: comments wrapper + per-comment cluster ----------------------

export function CommentsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const tagName = attrString(attributes.tagName, 'div').toLowerCase();
    const safeTag = (['div', 'section', 'article', 'aside', 'footer'].includes(tagName)
        ? tagName
        : 'div') as 'div' | 'section' | 'article' | 'aside' | 'footer';
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-comments', className]);

    const Tag = safeTag;
    return <Tag className={classes}>{children}</Tag>;
}

export function CommentTemplateBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-comment-template', className]);

    // The server-side CommentInliner clones the saved template once
    // per resolved comment and stamps each iteration's leaves via
    // CommentResolver, so `children` is already the expanded list
    // by the time it reaches this renderer.
    return <ol className={classes}>{children}</ol>;
}

export function CommentAuthorNameBlock({ attributes }: BlockRendererProps): JSX.Element {
    const isLink = attrBoolean(attributes.isLink, true);
    const linkTarget = attrString(attributes.linkTarget, '_self');
    const align = attrString(attributes.textAlign);
    const className = attrString(attributes.className);

    const name = attrString(attributes._resolvedAuthorName);
    const url = safeUrl(attrString(attributes._resolvedAuthorUrl));

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
        return (
            <div className={classes}>
                <a {...linkProps}>{name}</a>
            </div>
        );
    }
    return <div className={classes}>{name}</div>;
}

export function CommentAuthorAvatarBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attrString(attributes._resolvedAvatarUrl));
    const alt = attrString(attributes._resolvedAvatarAlt);
    const width = Math.max(1, attrInt(attributes.width, 96));
    const height = Math.max(1, attrInt(attributes.height, 96));
    const className = attrString(attributes.className);

    const classes = classList(['wp-block-comment-author-avatar', className]);

    if (url === '') {
        return <div className={classes} />;
    }
    return (
        <div className={classes}>
            <img src={url} alt={alt} width={width} height={height} loading="lazy" />
        </div>
    );
}

export function CommentContentBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.textAlign);
    const className = attrString(attributes.className);
    const content = attrString(attributes._resolvedContent);

    const classes = classList([
        'wp-block-comment-content',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    return <div className={classes} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function CommentDateBlock({ attributes }: BlockRendererProps): JSX.Element {
    const isLink = attrBoolean(attributes.isLink, true);
    const className = attrString(attributes.className);
    const datetime = attrString(attributes._resolvedDate);
    const formatted = attrString(attributes._resolvedDateFormatted, datetime);
    const url = safeUrl(attrString(attributes._resolvedPermalink));

    const classes = classList(['wp-block-comment-date', className]);

    const timeNode = datetime !== '' ? <time dateTime={datetime}>{formatted}</time> : <>{formatted}</>;

    return (
        <div className={classes}>
            {isLink && url !== '' ? <a href={url}>{timeNode}</a> : timeNode}
        </div>
    );
}

export function CommentEditLinkBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const url = safeUrl(attrString(attributes._resolvedEditLinkUrl));
    const label = attrString(attributes._resolvedEditLinkLabel, 'Edit');
    const linkTarget = attrString(attributes.linkTarget, '_self');
    const className = attrString(attributes.className);

    if (url === '') {
        return null;
    }

    const classes = classList(['wp-block-comment-edit-link', className]);
    const linkProps: Record<string, string> = { href: url };
    if (linkTarget === '_blank') {
        linkProps.target = '_blank';
        linkProps.rel = 'noopener noreferrer';
    }

    return (
        <div className={classes}>
            <a {...linkProps}>{label}</a>
        </div>
    );
}

export function CommentReplyLinkBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const url = safeUrl(attrString(attributes._resolvedReplyLinkUrl));
    const label = attrString(attributes._resolvedReplyLinkLabel, 'Reply');
    const className = attrString(attributes.className);

    if (url === '') {
        return null;
    }

    const classes = classList(['wp-block-comment-reply-link', className]);
    return (
        <div className={classes}>
            <a href={url}>{label}</a>
        </div>
    );
}

// --- Pass 2: post-comments-* metadata + pagination cluster --------------

export function PostCommentsFormBlock({ attributes }: BlockRendererProps): JSX.Element {
    // SPA contexts typically replace this with a real form. The
    // default render is a scaffolded placeholder so the saved tree
    // still emits something semantically correct.
    //
    // Element IDs are derived from React.useId() so multiple
    // instances on the same page (e.g. a post-with-comments
    // archive) get unique label-for/input-id pairings.
    const uid = useId();
    const authorId = `ve-comment-author-${uid}`;
    const emailId = `ve-comment-email-${uid}`;
    const contentId = `ve-comment-content-${uid}`;

    const className = attrString(attributes.className);
    const classes = classList(['wp-block-post-comments-form', 'comment-respond', className]);
    const postId = attrInt(attributes._resolvedPostId, 0);
    // Host apps redirect form submissions to their own form-handler
    // controller by stamping `_resolvedFormAction` on the block via
    // CommentInliner. The default points at cms-framework's REST
    // endpoint, which returns JSON — fine for SPAs, not for HTML form
    // submits where the user expects a redirect back to the post.
    const formAction = safeUrl(attrString(attributes._resolvedFormAction, '/api/v1/comments'));

    return (
        <div className={classes}>
            <h3 className="comment-reply-title">Leave a Comment</h3>
            <form action={formAction} method="post" className="comment-form">
                {postId > 0 ? <input type="hidden" name="post_id" value={postId} /> : null}
                <p className="comment-form-author">
                    <label htmlFor={authorId}>
                        Name <span aria-hidden="true">*</span>
                    </label>
                    <input id={authorId} name="author_name" type="text" required />
                </p>
                <p className="comment-form-email">
                    <label htmlFor={emailId}>
                        Email <span aria-hidden="true">*</span>
                    </label>
                    <input id={emailId} name="author_email" type="email" required />
                </p>
                <p className="comment-form-comment">
                    <label htmlFor={contentId}>
                        Comment <span aria-hidden="true">*</span>
                    </label>
                    <textarea id={contentId} name="content" rows={6} required />
                </p>
                <p className="form-submit">
                    {/*
                     * Deliberately no `name="submit"` — that attribute
                     * shadows the form's `.submit` property in the DOM
                     * and breaks any JS that hooks the form.
                     */}
                    <input type="submit" className="submit" value="Post Comment" />
                </p>
            </form>
        </div>
    );
}

export function PostCommentsCountBlock({ attributes }: BlockRendererProps): JSX.Element {
    const count = attrInt(attributes._resolvedCommentCount, 0);
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-post-comments-count', className]);
    return <div className={classes}>{count}</div>;
}

export function PostCommentsLinkBlock({ attributes }: BlockRendererProps): JSX.Element {
    const count = attrInt(attributes._resolvedCommentCount, 0);
    const url = safeUrl(attrString(attributes._resolvedCommentsUrl));
    const fallbackLabel = count === 1 ? '1 Comment' : `${count} Comments`;
    const label = attrString(attributes._resolvedCommentsLabel, fallbackLabel);
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-post-comments-link', className]);

    if (url === '') {
        return <div className={classes}>{label}</div>;
    }
    return (
        <div className={classes}>
            <a href={url}>{label}</a>
        </div>
    );
}

export function PostCommentsTitleBlock({ attributes }: BlockRendererProps): JSX.Element {
    const level = Math.max(1, Math.min(6, attrInt(attributes.level, 2)));
    const showCount = attrBoolean(attributes.showCommentsCount, true);
    const count = attrInt(attributes._resolvedCommentCount, 0);
    const fallback = count === 0 ? 'No Comments' : count === 1 ? '1 Comment' : `${count} Comments`;
    const title = showCount ? attrString(attributes._resolvedCommentsTitle, fallback) : 'Comments';
    const align = attrString(attributes.textAlign);
    const className = attrString(attributes.className);

    const Tag = `h${level}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
    const classes = classList([
        'wp-block-post-comments-title',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    return <Tag className={classes}>{title}</Tag>;
}

export function CommentsPaginationBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-comments-pagination', className]);
    return (
        <nav className={classes} aria-label="Comments pagination">
            {children}
        </nav>
    );
}

export function CommentsPaginationNextBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attrString(attributes._resolvedNextPageUrl));
    const label = attrString(attributes.label, 'Newer Comments');
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-comments-pagination-next', className]);

    if (url === '') {
        return <span className={classes}>{label}</span>;
    }
    return (
        <a className={classes} href={url}>
            {label} &rarr;
        </a>
    );
}

export function CommentsPaginationPreviousBlock({ attributes }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attrString(attributes._resolvedPreviousPageUrl));
    const label = attrString(attributes.label, 'Older Comments');
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-comments-pagination-previous', className]);

    if (url === '') {
        return <span className={classes}>{label}</span>;
    }
    return (
        <a className={classes} href={url}>
            &larr; {label}
        </a>
    );
}

interface ResolvedPage {
    readonly number?: unknown;
    readonly url?: unknown;
}

export function CommentsPaginationNumbersBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-comments-pagination-numbers', className]);
    const current = attrInt(attributes._resolvedCurrentPage, 1);
    const rawPages = attributes._resolvedPageNumbers;
    const pages: ResolvedPage[] = Array.isArray(rawPages) ? (rawPages as ResolvedPage[]) : [];

    return (
        <div className={classes}>
            {pages.map((page, idx) => {
                const number =
                    typeof page.number === 'number' && Number.isFinite(page.number) ? page.number : 0;
                if (number === 0) {
                    return null;
                }
                const href = safeUrl(typeof page.url === 'string' ? page.url : '');
                const isCurrent = number === current;

                // Only the actual current page gets aria-current="page".
                // A page that's missing its URL still renders as a
                // non-interactive span (no link target) but should not
                // claim to be the current page.
                if (isCurrent) {
                    return (
                        <span
                            key={`pg-${idx}-${number}`}
                            className="page-numbers current"
                            aria-current="page"
                        >
                            {number}
                        </span>
                    );
                }
                if (href === '') {
                    return (
                        <span key={`pg-${idx}-${number}`} className="page-numbers">
                            {number}
                        </span>
                    );
                }
                return (
                    <a key={`pg-${idx}-${number}`} className="page-numbers" href={href}>
                        {number}
                    </a>
                );
            })}
        </div>
    );
}
