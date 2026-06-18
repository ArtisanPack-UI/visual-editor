/**
 * `core/query` and `core/post-template` renderers.
 *
 * Both blocks operate on an already-expanded inner-block tree — see
 * {@link inlineQueries} for the pre-walk that replaces every
 * `core/query` block with one stamped copy of its template per result.
 * The renderers themselves just emit the wrapping markup and pass
 * children through.
 *
 * If the inliner could not resolve the query (no resolved set passed
 * for the matching `queryId`), it stamps `_resolutionError` and the
 * wrapper renders empty in production so the surrounding layout stays
 * intact.
 */

import type { JSX, ReactNode } from 'react';
import { attrString, classList, postTemplateItemSpanClasses } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

interface WrapperProps {
    children: ReactNode;
    className: string;
    'data-ve-resolution-error'?: string;
}

/**
 * Coerce a host-supplied `columns` attribute into a safe integer in [1, 12].
 * Rejects NaN / Infinity / fractions / non-numbers so the emitted
 * `columns-N` class and `data-ap-cols` attribute always carry a value the
 * stylesheet + JS bootstrap can use.
 */
function clampColumns(value: unknown, fallback: number): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return fallback;
    }
    const truncated = Math.trunc(value);
    if (truncated < 1) {
        return 1;
    }
    if (truncated > 12) {
        return 12;
    }
    return truncated;
}

function isDevelopment(): boolean {
    if (typeof process === 'undefined') {
        return false;
    }

    const env = process.env;

    if (env === undefined || env === null) {
        return false;
    }

    return env.NODE_ENV !== 'production';
}

export function QueryBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const resolutionError = attrString(attributes._resolutionError);
    const hasError = resolutionError !== '';
    const baseClasses = classList(['wp-block-query', className]);

    const wrapperProps: WrapperProps = {
        children: hasError ? null : children,
        className: baseClasses,
    };

    if (hasError && isDevelopment()) {
        wrapperProps['data-ve-resolution-error'] = resolutionError;
    }

    return <div {...wrapperProps} />;
}

export function PostTemplateBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const layout = attrString(attributes.layout);
    const layoutType = attrString(attributes.layoutType);
    const isGrid = layout === 'grid' || layoutType === 'grid';
    const isMasonry = layout === 'masonry';
    const usesColumns = isGrid || isMasonry;
    const columns = clampColumns(attributes.columns, 3);

    const classes = classList([
        'wp-block-post-template',
        // Masonry layers `is-layout-grid` underneath `is-layout-masonry`
        // so the existing grid CSS provides the baseline layout, and
        // the masonry stylesheet adds `grid-template-rows: masonry` on
        // top via `@supports` for browsers that ship native CSS Grid
        // masonry. The JS bootstrap takes over for the rest.
        (isGrid || isMasonry) ? 'is-layout-grid' : '',
        isMasonry ? 'is-layout-masonry' : '',
        !usesColumns ? 'is-layout-flow' : '',
        usesColumns ? `columns-${columns}` : '',
        className,
    ]);

    const props: Record<string, unknown> = { className: classes };
    if (isMasonry) {
        props['data-ap-cols'] = columns;
    }

    return <ul {...props}>{children}</ul>;
}

/**
 * `artisanpack/post-variant` (#591) is stripped from the inner-block
 * tree by the server-side `QueryInliner` before the renderer ever
 * walks it: variant children survive only as the per-iteration
 * template clone, never as their own block. The renderer still
 * registers the block so the parity check stays green and so any
 * client-rendered preview tree (which can include un-inlined variants)
 * has somewhere to fall through.
 */
export function PostVariantBlock({ children }: BlockRendererProps): JSX.Element {
    return <>{children}</>;
}

export function PostTemplateItemBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    // Coerce numeric strings ("123") to numbers so the `post-{id}` id stamps
    // regardless of whether the host serialized the attribute as a number or a
    // string — matches the Blade partial's `(int)` cast.
    const rawPostId = attributes.postId;
    const parsedPostId = typeof rawPostId === 'number' ? rawPostId : Number(rawPostId);
    const postId = Number.isFinite(parsedPostId) ? parsedPostId : 0;
    const className = attrString(attributes.className);
    const spanClasses = postTemplateItemSpanClasses(attributes._resolvedGridSpan);

    const classes = classList(['wp-block-post-template-item', className, ...spanClasses]);

    return <li id={postId > 0 ? `post-${postId}` : undefined} className={classes}>{children}</li>;
}
