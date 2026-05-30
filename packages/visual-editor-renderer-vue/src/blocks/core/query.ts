/**
 * `core/query` and `core/post-template` Vue renderers.
 *
 * Operate on an already-expanded inner-block tree — see
 * {@link inlineQueries} for the pre-walk that replaces every
 * `core/query` block with one stamped copy of its template per result.
 * The renderers themselves emit the wrapping markup and pass children
 * through.
 *
 * If the inliner could not resolve the query, it stamps
 * `_resolutionError` and the wrapper renders empty in production so the
 * surrounding layout stays intact.
 */

import { defineComponent, h } from 'vue';
import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

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

export const QueryBlock = defineComponent({
    name: 'QueryBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            const resolutionError = attrString(props.attributes._resolutionError);
            const hasError = resolutionError !== '';

            const attrs: Record<string, unknown> = {
                class: classList(['wp-block-query', className]),
            };

            if (hasError && isDevelopment()) {
                attrs['data-ve-resolution-error'] = resolutionError;
            }

            return h('div', attrs, hasError ? null : slots.default?.());
        };
    },
});

export const PostTemplateBlock = defineComponent({
    name: 'PostTemplateBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            const layout = attrString(props.attributes.layout);
            const layoutType = attrString(props.attributes.layoutType);
            const isGrid = layout === 'grid' || layoutType === 'grid';
            const columns = typeof props.attributes.columns === 'number' ? props.attributes.columns : 3;

            return h(
                'ul',
                {
                    class: classList([
                        'wp-block-post-template',
                        isGrid ? 'is-layout-grid' : 'is-layout-flow',
                        isGrid ? `columns-${columns}` : '',
                        className,
                    ]),
                },
                slots.default?.()
            );
        };
    },
});

export const PostTemplateItemBlock = defineComponent({
    name: 'PostTemplateItemBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const postId = typeof props.attributes.postId === 'number' ? props.attributes.postId : 0;
            const className = attrString(props.attributes.className);

            const attrs: Record<string, unknown> = {
                class: classList(['wp-block-post-template-item', className]),
            };

            if (postId > 0) {
                attrs.id = `post-${postId}`;
            }

            return h('li', attrs, slots.default?.());
        };
    },
});
