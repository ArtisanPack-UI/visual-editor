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
import { attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

interface WrapperProps {
    children: ReactNode;
    className: string;
    'data-ve-resolution-error'?: string;
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
    const columns = typeof attributes.columns === 'number' ? attributes.columns : 3;

    const classes = classList([
        'wp-block-post-template',
        isGrid ? 'is-layout-grid' : 'is-layout-flow',
        isGrid ? `columns-${columns}` : '',
        className,
    ]);

    return <ul className={classes}>{children}</ul>;
}

export function PostTemplateItemBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    // Coerce numeric strings ("123") to numbers so the `post-{id}` id stamps
    // regardless of whether the host serialized the attribute as a number or a
    // string — matches the Blade partial's `(int)` cast.
    const rawPostId = attributes.postId;
    const parsedPostId = typeof rawPostId === 'number' ? rawPostId : Number(rawPostId);
    const postId = Number.isFinite(parsedPostId) ? parsedPostId : 0;
    const className = attrString(attributes.className);

    const classes = classList(['wp-block-post-template-item', className]);

    return <li id={postId > 0 ? `post-${postId}` : undefined} className={classes}>{children}</li>;
}
