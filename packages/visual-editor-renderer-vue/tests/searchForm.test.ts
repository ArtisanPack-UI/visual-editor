/**
 * Search form action + prefill (#961). Mirrors the Blade renderer's
 * `SearchFormActionTest`, minus the request-dependent paths: the Vue
 * renderer omits the action for `current` and reads `_resolvedSearchValue`.
 */

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { sameSitePath, searchFormAction } from '../src/support/searchForm';
import { makeBlock, normalizeHtml } from './helpers';
import type { Block } from '../src/types';

function mountTree(tree: unknown) {
    return mount(BlockTree, {
        props: { tree: tree as Block[] },
    });
}

function renderTree(tree: unknown): string {
    return normalizeHtml(mountTree(tree).html());
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
    it('prefills from _resolvedSearchValue', () => {
        const input = mountTree([
            makeBlock('artisanpack/search', { _resolvedSearchValue: '"><script>x</script>' }),
        ]).find('input[type="search"]');

        expect((input.element as HTMLInputElement).value).toBe('"><script>x</script>');
        expect(renderTree([makeBlock('artisanpack/search')])).not.toContain('<script>');
    });

    it('keeps the hidden post_type input on search filters', () => {
        expect(
            renderTree([makeBlock('artisanpack/search-filters', { postType: 'package', action: 'current' })])
        ).toContain('<input type="hidden" name="post_type" value="package">');
    });
});

function formClasses(html: string): string {
    return /<form[^>]*class="([^"]*)"/.exec(html)?.[1] ?? '';
}

describe('search button position', () => {
    it.each([
        [{}, 'wp-block-search wp-block-search__button-outside wp-block-search__text-button'],
        [{ buttonPosition: 'button-inside' }, 'wp-block-search wp-block-search__button-inside wp-block-search__text-button'],
        [
            { buttonPosition: 'button-inside', buttonUseIcon: true },
            'wp-block-search wp-block-search__button-inside wp-block-search__icon-button',
        ],
        [{ buttonPosition: 'no-button', buttonUseIcon: true }, 'wp-block-search wp-block-search__no-button'],
        [{ buttonPosition: 'button-only' }, 'wp-block-search wp-block-search__button-only wp-block-search__text-button'],
        [{ buttonPosition: 'sideways' }, 'wp-block-search wp-block-search__button-outside wp-block-search__text-button'],
        [{ showLabel: false }, 'wp-block-search wp-block-search__button-outside wp-block-search__text-button'],
    ])('%o → %s', (attributes, classes) => {
        expect(formClasses(renderTree([makeBlock('artisanpack/search', attributes)]))).toBe(classes);
    });

    it('renders no submit button for no-button', () => {
        const html = renderTree([makeBlock('artisanpack/search', { buttonPosition: 'no-button' })]);

        expect(html).not.toContain('<button');
        expect(html).toContain('class="wp-block-search__input"');
    });

    it('keeps a hidden label for screen readers', () => {
        const html = renderTree([
            makeBlock('artisanpack/search', { showLabel: false, label: 'Search posts' }),
        ]);

        expect(html).toMatch(
            /<label class="wp-block-search__label screen-reader-text" for="wp-block-search-input-[\w-]+">Search posts<\/label>/
        );
    });
});
