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
import { attrString, classList, postTemplateItemSpanClasses } from '../../support/attributes';
import { blockRendererProps } from '../shared';

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
            const isMasonry = layout === 'masonry';
            const usesColumns = isGrid || isMasonry;
            const columns = clampColumns(props.attributes.columns, 3);

            const attrs: Record<string, unknown> = {
                class: classList([
                    'wp-block-post-template',
                    // Masonry layers `is-layout-grid` underneath
                    // `is-layout-masonry` so the existing grid CSS
                    // provides the baseline layout, and the masonry
                    // stylesheet adds `grid-template-rows: masonry` on
                    // top via `@supports` for browsers that ship native
                    // CSS Grid masonry. The JS bootstrap packs the rest.
                    (isGrid || isMasonry) ? 'is-layout-grid' : '',
                    isMasonry ? 'is-layout-masonry' : '',
                    !usesColumns ? 'is-layout-flow' : '',
                    usesColumns ? `columns-${columns}` : '',
                    className,
                ]),
            };

            if (isMasonry) {
                attrs['data-ap-cols'] = columns;
            }

            return h('ul', attrs, slots.default?.());
        };
    },
});

/**
 * `artisanpack/post-variant` (#591) is stripped from the inner-block
 * tree by the server-side `QueryInliner` before render — the variant's
 * children survive only as the per-iteration clone. The renderer
 * registers the block as a pass-through so the parity check stays
 * green and so any client-rendered preview tree has a fall-through.
 */
export const PostVariantBlock = defineComponent({
    name: 'PostVariantBlock',
    props: blockRendererProps,
    setup(_props, { slots }) {
        return () => slots.default?.();
    },
});

export const PostTemplateItemBlock = defineComponent({
    name: 'PostTemplateItemBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            // Coerce numeric strings ("123") so the `post-{id}` id stamps
            // regardless of whether the host serialized postId as a number or
            // a string — matches the Blade partial's `(int)` cast.
            const rawPostId = props.attributes.postId;
            const parsedPostId =
                typeof rawPostId === 'number' ? rawPostId : Number(rawPostId);
            const postId = Number.isFinite(parsedPostId) ? parsedPostId : 0;
            const className = attrString(props.attributes.className);
            const spanClasses = postTemplateItemSpanClasses(
                props.attributes._resolvedGridSpan,
            );

            const attrs: Record<string, unknown> = {
                class: classList(['wp-block-post-template-item', className, ...spanClasses]),
            };

            if (postId > 0) {
                attrs.id = `post-${postId}`;
            }

            return h('li', attrs, slots.default?.());
        };
    },
});
