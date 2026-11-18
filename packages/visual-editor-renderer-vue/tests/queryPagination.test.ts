/**
 * Query Pagination arrows, labels and the windowed page list — Vue
 * renderer (#1130). QueryInliner stamps the wrapper's Arrow / Show label
 * settings onto previous / next and windows the page numbers with
 * `{ dots: true }` gaps; these cases mirror the Blade partials.
 */

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { makeBlock, normalizeHtml } from './helpers';
import type { Block } from '../src/types';

function renderTree(tree: unknown): string {
    const wrapper = mount(BlockTree, { props: { tree: tree as Block[] } });

    return normalizeHtml(wrapper.html());
}

const next = (extra: Record<string, unknown>) =>
    renderTree([makeBlock('artisanpack/query-pagination-next', { _resolvedNextPageUrl: '/blog?page=2', ...extra })]);
const previous = (extra: Record<string, unknown>) =>
    renderTree([makeBlock('artisanpack/query-pagination-previous', { _resolvedPreviousPageUrl: '/blog?page=1', ...extra })]);

describe('query pagination previous / next (#1130)', () => {
    it('shows only the label when the arrow is none', () => {
        expect(next({ _resolvedPaginationArrow: 'none' })).toContain('href="/blog?page=2">Next Page</a>');
        expect(previous({})).toContain('href="/blog?page=1">Previous Page</a>');
    });

    it('adds the arrow or chevron after next and before previous', () => {
        expect(next({ _resolvedPaginationArrow: 'arrow' })).toContain(
            'Next Page<span class="wp-block-query-pagination-next-arrow is-arrow-arrow" aria-hidden="true">→</span></a>'
        );
        expect(previous({ _resolvedPaginationArrow: 'chevron' })).toContain(
            '"><span class="wp-block-query-pagination-previous-arrow is-arrow-chevron" aria-hidden="true">«</span>Previous Page</a>'
        );
    });

    it('keeps a hidden label for screen readers, but never hides it without an arrow', () => {
        expect(next({ _resolvedPaginationArrow: 'chevron', _resolvedShowLabel: false })).toContain(
            '<span class="screen-reader-text">Next Page</span><span class="wp-block-query-pagination-next-arrow is-arrow-chevron" aria-hidden="true">»</span>'
        );
        expect(next({ _resolvedPaginationArrow: 'none', _resolvedShowLabel: false })).toContain('>Next Page</a>');
    });
});

describe('query pagination numbers (#1130)', () => {
    it('renders a gap as an ellipsis', () => {
        const html = renderTree([
            makeBlock('artisanpack/query-pagination-numbers', {
                _resolvedCurrentPage: 1,
                _resolvedPageNumbers: [
                    { number: 1, url: '/blog?page=1' },
                    { number: 2, url: '/blog?page=2' },
                    { dots: true },
                    { number: 41, url: '/blog?page=41' },
                ],
            }),
        ]);

        expect(html).toContain(
            '<span class="page-numbers current" aria-current="page">1</span><a class="page-numbers" href="/blog?page=2">2</a><span class="page-numbers dots">…</span><a class="page-numbers" href="/blog?page=41">41</a>'
        );
    });
});
