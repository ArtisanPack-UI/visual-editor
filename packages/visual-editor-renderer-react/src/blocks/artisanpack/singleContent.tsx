/**
 * React renderer for the `artisanpack/single-content` block (#501).
 *
 * Mirrors the Blade partial and the Vue renderer. Server-side
 * `QueryInliner` resolves the chosen post (or falls back to the host
 * post) through `QueryResolverContract` and re-stamps the inner-block
 * tree against it. This renderer only owns the wrapper: emits the
 * `<div>` shell when `_resolvedHasPost` is true, otherwise nothing.
 */

import type { ReactElement } from 'react';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

export function SingleContentBlock({
    attributes,
    children,
}: BlockRendererProps): ReactElement | null {
    const hasPost = attrBoolean(attributes._resolvedHasPost, false);

    if (!hasPost) {
        return null;
    }

    const className = attrString(attributes.className);
    const classes = classList(['ap-single-content', className]);

    return <div className={classes}>{children}</div>;
}
