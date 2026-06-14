/**
 * React renderers for the post-navigation container family (#499).
 *
 *   - artisanpack/next-post
 *   - artisanpack/previous-post
 *
 * Container blocks: server-side `PostResolver` resolves the adjacent
 * post and re-stamps the inner-block tree against it. This renderer
 * only owns the wrapper: when `_resolvedHasAdjacent` is true, emit the
 * `<div>` shell with the `wp-block-…` + `navigation-post` classes; when
 * false (no neighbor in the chosen direction), emit nothing — matches
 * the Blade + Vue counterparts.
 */

import type { ReactElement } from 'react';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

function renderAdjacentContainer(
    wrapperClass: string,
    { attributes, children }: BlockRendererProps
): ReactElement | null {
    const hasAdjacent = attrBoolean(attributes._resolvedHasAdjacent, false);

    if (!hasAdjacent) {
        return null;
    }

    const className = attrString(attributes.className);
    const classes = classList([wrapperClass, 'navigation-post', className]);

    return <div className={classes}>{children}</div>;
}

export function NextPostBlock(props: BlockRendererProps): ReactElement | null {
    return renderAdjacentContainer('wp-block-artisanpack-next-post', props);
}

export function PreviousPostBlock(props: BlockRendererProps): ReactElement | null {
    return renderAdjacentContainer('wp-block-artisanpack-previous-post', props);
}
