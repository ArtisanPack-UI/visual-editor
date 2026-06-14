/**
 * React renderer for the `artisanpack/related-posts` block (#501).
 *
 * Mirrors the Blade partial and the Vue renderer. Server-side
 * `QueryInliner` resolves N related posts for the host entry and clones
 * the saved inner-block tree once per result with `_resolved*` stamps
 * applied through `PostResolver`. When zero results matched the
 * resolver, this renderer emits nothing so the surrounding layout
 * collapses cleanly.
 */

import type { ReactElement } from 'react';

import { attrInt, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

function clampColumns(value: unknown, fallback: number): number {
    const parsed = attrInt(value, fallback);
    if (parsed < 1) {
        return 1;
    }
    if (parsed > 4) {
        return 4;
    }
    return parsed;
}

export function RelatedPostsBlock({
    attributes,
    children,
}: BlockRendererProps): ReactElement | null {
    const items = attrInt(attributes._resolvedItems, 0);

    if (items <= 0) {
        return null;
    }

    const columns = clampColumns(attributes.numColumns, 1);
    const className = attrString(attributes.className);

    const classes = classList([
        'ap-related-posts',
        `ap-related-posts-has-${columns}-columns`,
        className,
    ]);

    return <div className={classes}>{children}</div>;
}
