/**
 * Query-family renderers (#521) — Vue.
 *
 * Six dynamic blocks mirroring the React + Blade implementations.
 * Each leaf reads `_resolved*` attributes stamped by visual-editor's
 * QueryInliner before the tree reaches the renderer.
 */

import { defineComponent, h } from 'vue';

import { attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

export const QueryNoResultsBlock = defineComponent({
    name: 'QueryNoResultsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            return h(
                'div',
                { class: classList(['wp-block-query-no-results', className]) },
                slots.default?.()
            );
        };
    },
});

export const QueryPaginationBlock = defineComponent({
    name: 'QueryPaginationBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            return h(
                'nav',
                {
                    class: classList(['wp-block-query-pagination', className]),
                    'aria-label': 'Pagination',
                },
                slots.default?.()
            );
        };
    },
});

export const QueryPaginationNextBlock = defineComponent({
    name: 'QueryPaginationNextBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedNextPageUrl);
            const label = attrString(props.attributes.label, 'Next Page');
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-query-pagination-next', className]);
            if (url === '') {
                return h('span', { class: classes }, label);
            }
            return h('a', { class: classes, href: url }, `${label} →`);
        };
    },
});

export const QueryPaginationPreviousBlock = defineComponent({
    name: 'QueryPaginationPreviousBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const url = safeUrl(props.attributes._resolvedPreviousPageUrl);
            const label = attrString(props.attributes.label, 'Previous Page');
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-query-pagination-previous', className]);
            if (url === '') {
                return h('span', { class: classes }, label);
            }
            return h('a', { class: classes, href: url }, `← ${label}`);
        };
    },
});

interface ResolvedPage {
    readonly number?: unknown;
    readonly url?: unknown;
}

export const QueryPaginationNumbersBlock = defineComponent({
    name: 'QueryPaginationNumbersBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-query-pagination-numbers', className]);
            const current = attrInt(props.attributes._resolvedCurrentPage, 1);
            const raw = props.attributes._resolvedPageNumbers;
            const pages: ResolvedPage[] = Array.isArray(raw) ? (raw as ResolvedPage[]) : [];

            const children = pages
                .map((page, idx) => {
                    // Accept either a number or a numeric string for `page.number` so the
                    // React / Vue renderers stay in parity with the Blade partial, whose
                    // `is_numeric()` check passes for both `2` and `"2"`. Hosts that pass
                    // JSON-decoded payloads through PHP's `json_encode` typically get strings.
                    const rawNumber = page.number;
                    const parsed =
                        typeof rawNumber === 'number'
                            ? rawNumber
                            : typeof rawNumber === 'string' && rawNumber.trim() !== ''
                              ? Number(rawNumber)
                              : Number.NaN;
                    const number = Number.isFinite(parsed) ? parsed : 0;
                    if (number === 0) {
                        return null;
                    }
                    const href = safeUrl(typeof page.url === 'string' ? page.url : '');
                    const isCurrent = number === current;

                    // Only the actual current page gets aria-current="page".
                    // A non-current page with an empty href still renders
                    // as a plain span (no link target) but must not claim
                    // to be the current page. Mirrors Blade + React parity.
                    if (isCurrent) {
                        return h(
                            'span',
                            {
                                key: `pg-${idx}-${number}`,
                                class: 'page-numbers current',
                                'aria-current': 'page',
                            },
                            String(number)
                        );
                    }
                    if (href === '') {
                        return h(
                            'span',
                            { key: `pg-${idx}-${number}`, class: 'page-numbers' },
                            String(number)
                        );
                    }
                    return h(
                        'a',
                        { key: `pg-${idx}-${number}`, class: 'page-numbers', href },
                        String(number)
                    );
                })
                .filter((child) => child !== null);

            return h('div', { class: classes }, children);
        };
    },
});

export const QueryTitleBlock = defineComponent({
    name: 'QueryTitleBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const title = attrString(props.attributes._resolvedQueryTitle);
            if (title === '') {
                return null;
            }
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-query-title', className]);
            // Validate `level` against the WP heading range (0 = paragraph,
            // 1-6 = h1-h6) and fall back to h1 for out-of-range values —
            // mirrors the Blade partial's tag allow-list so the React / Vue
            // / Blade renderers stay byte-equivalent.
            const rawLevel = attrInt(props.attributes.level, 1);
            const tag = (
                rawLevel === 0
                    ? 'p'
                    : rawLevel >= 1 && rawLevel <= 6
                      ? (`h${rawLevel}` as const)
                      : 'h1'
            ) as 'p' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

            return h(tag, { class: classes }, title);
        };
    },
});
