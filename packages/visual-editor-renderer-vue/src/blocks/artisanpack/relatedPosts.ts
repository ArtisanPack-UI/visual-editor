/**
 * Vue renderer for the `artisanpack/related-posts` block (#501).
 *
 * Mirrors the Blade partial and the React renderer. Server-side
 * `QueryInliner` resolves N related posts for the host entry and
 * clones the saved inner-block tree once per result with `_resolved*`
 * stamps applied through `PostResolver`. When zero results matched the
 * resolver, this renderer emits nothing so the surrounding layout
 * collapses cleanly.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrInt, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

export const RelatedPostsBlock = defineComponent({
    name: 'RelatedPostsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return (): VNode | null => {
            const items = attrInt(props.attributes._resolvedItems, 0);

            if (items <= 0) {
                return null;
            }

            const className = attrString(props.attributes.className);
            const classes = classList([ 'ap-related-posts', className ]);

            return h('div', { class: classes }, slots.default?.());
        };
    },
});
