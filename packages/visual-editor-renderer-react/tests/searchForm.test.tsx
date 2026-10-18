/**
 * Search form action + prefill (#961). Mirrors the Blade renderer's
 * `SearchFormActionTest`, minus the request-dependent paths: the React
 * renderer omits the action for `current` and reads `_resolvedSearchValue`.
 */

import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { sameSitePath, searchFormAction } from '../src/support/searchForm';
import { makeBlock, normalizeHtml } from './helpers';

function renderTree(tree: unknown): string {
    const { container } = render(
        <BlockTree tree={tree as Parameters<typeof BlockTree>[0]['tree']} />
    );

    return normalizeHtml(container.innerHTML);
}

const FORM_BLOCKS = ['artisanpack/search', 'core/search', 'artisanpack/search-filters'];

describe('searchFormAction', () => {
    it('defaults to the site search URL', () => {
        expect(searchFormAction({})).toBe('/search');
        expect(searchFormAction({ action: '' })).toBe('/search');
    });

    it('omits the action for the current page', () => {
        expect(searchFormAction({ action: 'current' })).toBeUndefined();
    });

    it('accepts a same-site path', () => {
        expect(searchFormAction({ action: ' /blog ' })).toBe('/blog');
    });

    it.each([
        'https://evil.test/search',
        '//evil.test/search',
        '/\\evil.test',
        'javascript:alert(1)//evil',
        'evil/search',
        '/evil path',
    ])('rejects %s', (action) => {
        expect(sameSitePath(action)).toBe('');
        expect(searchFormAction({ action })).toBe('/search');
    });

    it('lets a safe stamped _resolvedFormAction win', () => {
        expect(
            searchFormAction({ action: '/blog', _resolvedFormAction: 'https://example.test/archive' })
        ).toBe('https://example.test/archive');
        expect(searchFormAction({ action: '/blog', _resolvedFormAction: 'javascript:alert(1)' })).toBe(
            '/blog'
        );
    });
});

describe.each(FORM_BLOCKS)('%s form action', (name) => {
    it('renders the resolved action', () => {
        expect(renderTree([makeBlock(name)])).toContain('action="/search"');
        expect(renderTree([makeBlock(name, { action: '/blog' })])).toContain('action="/blog"');
        expect(renderTree([makeBlock(name, { action: 'https://evil.test' })])).toContain(
            'action="/search"'
        );
        expect(
            renderTree([makeBlock(name, { action: '/blog', _resolvedFormAction: '/stamped' })])
        ).toContain('action="/stamped"');
    });

    it('omits the action attribute for the current page', () => {
        expect(renderTree([makeBlock(name, { action: 'current' })])).not.toContain('action=');
    });
});

describe('search input prefill', () => {
    it('prefills from _resolvedSearchValue, escaped', () => {
        const html = renderTree([
            makeBlock('artisanpack/search', { _resolvedSearchValue: '"><script>x</script>' }),
        ]);

        expect(html).toContain('value="&quot;><script>x</script>"');
        expect(html).not.toContain('"><script>');
    });

    it('keeps the hidden post_type input on search filters', () => {
        expect(
            renderTree([makeBlock('artisanpack/search-filters', { postType: 'package', action: 'current' })])
        ).toContain('<input type="hidden" name="post_type" value="package">');
    });
});
