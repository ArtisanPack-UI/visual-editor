/**
 * Vue renderers for the post-navigation container family (#499).
 *
 *   - artisanpack/next-post
 *   - artisanpack/previous-post
 *
 * Container blocks: server-side `PostResolver` resolves the adjacent
 * post and re-stamps the inner-block tree against it. This renderer
 * only owns the wrapper: when `_resolvedHasAdjacent` is true, emit the
 * `<div>` shell with the `wp-block-…` + `navigation-post` classes; when
 * false (no neighbor in the chosen direction), emit nothing — matches
 * the Blade + React counterparts.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

function adjacentContainer(name: string, wrapperClass: string) {
    return defineComponent({
        name,
        props: blockRendererProps,
        setup(props, { slots }) {
            return (): VNode | null => {
                const hasAdjacent = attrBoolean(
                    props.attributes._resolvedHasAdjacent,
                    false
                );

                if (!hasAdjacent) {
                    return null;
                }

                const className = attrString(props.attributes.className);
                const classes = classList([
                    wrapperClass,
                    'navigation-post',
                    className,
                ]);

                return h('div', { class: classes }, slots.default?.());
            };
        },
    });
}

export const NextPostBlock = adjacentContainer(
    'NextPostBlock',
    'wp-block-artisanpack-next-post'
);

export const PreviousPostBlock = adjacentContainer(
    'PreviousPostBlock',
    'wp-block-artisanpack-previous-post'
);
