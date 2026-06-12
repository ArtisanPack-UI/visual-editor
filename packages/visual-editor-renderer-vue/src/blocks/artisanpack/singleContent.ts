/**
 * Vue renderer for the `artisanpack/single-content` block (#501).
 *
 * Mirrors the Blade partial and the React renderer. Server-side
 * `QueryInliner` resolves the chosen post (or falls back to the host
 * post) through `QueryResolverContract` and re-stamps the inner-block
 * tree against it. This renderer only owns the wrapper: emits the
 * `<div>` shell when `_resolvedHasPost` is true, otherwise nothing.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

export const SingleContentBlock = defineComponent({
    name: 'SingleContentBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return (): VNode | null => {
            const hasPost = attrBoolean(props.attributes._resolvedHasPost, false);

            if (!hasPost) {
                return null;
            }

            const className = attrString(props.attributes.className);
            const classes = classList(['ap-single-content', className]);

            return h('div', { class: classes }, slots.default?.());
        };
    },
});
