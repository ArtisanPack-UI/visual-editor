/**
 * Tests for the link selector's content search (#1042).
 */

import { afterEach, describe, expect, it, vi } from 'vitest';

import { resetBindingsApiConfig, setBindingsApiConfig } from '../bindings/config';
import { editorSettings } from '../editor-settings';
import { fetchLinkSuggestions, mapLinkSuggestions } from '../link-suggestions';

function stubFetch(body: unknown, ok = true): ReturnType<typeof vi.fn> {
    const fetchStub = vi.fn().mockResolvedValue({ ok, json: async () => body });

    vi.stubGlobal('fetch', fetchStub);

    return fetchStub;
}

const page = { id: 4, title: 'About', url: 'https://example.com/about', type: 'page' };
const post = { id: 9, title: 'News', url: 'https://example.com/blog/news', type: 'post' };
const book = { id: 'b-1', title: 'Atlas', url: '/books/atlas', type: 'book' };
const guide = { id: 2, title: 'Guide', url: '/guide', type: 'guide' };

describe('mapLinkSuggestions', () => {
    it('adds the kind LinkControl expects and keeps custom types', () => {
        expect(mapLinkSuggestions([page, book])).toEqual([
            { ...page, kind: 'post-type' },
            { ...book, kind: 'post-type' },
        ]);
    });

    it.each([
        ['a null url', { ...page, url: null }],
        ['an empty url', { ...page, url: '' }],
        ['a missing title', { id: 1, url: '/x', type: 'page' }],
        ['a missing id', { title: 'X', url: '/x', type: 'page' }],
        ['a null row', null],
    ])('drops a row with %s', (_label, row) => {
        expect(mapLinkSuggestions([row])).toEqual([]);
    });

    it('returns nothing for a non-array body', () => {
        expect(mapLinkSuggestions(undefined)).toEqual([]);
    });
});

describe('fetchLinkSuggestions', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        resetBindingsApiConfig();
    });

    it('searches under the API base the host configured', async () => {
        const fetchStub = stubFetch({ data: [] });

        setBindingsApiConfig({ apiBase: '/custom/api/' });

        await fetchLinkSuggestions('about');

        expect(String(fetchStub.mock.calls[0][0])).toMatch(/^\/custom\/api\/search\/links\?q=/);
    });

    it('searches the links endpoint with an encoded query', async () => {
        const fetchStub = stubFetch({ data: [page] });

        await expect(fetchLinkSuggestions('a&b')).resolves.toEqual([
            { ...page, kind: 'post-type' },
        ]);

        expect(fetchStub).toHaveBeenCalledWith(
            '/visual-editor/api/search/links?q=a%26b',
            expect.objectContaining({ credentials: 'same-origin' })
        );
    });

    it('limits initial suggestions', async () => {
        stubFetch({ data: [page, post, book, guide] });

        const suggestions = await fetchLinkSuggestions('', { isInitialSuggestions: true });

        expect(suggestions.map(({ id }) => id)).toEqual([page.id, post.id, book.id]);
    });

    it('does not limit results for a typed search', async () => {
        stubFetch({ data: [page, post, book, guide] });

        await expect(fetchLinkSuggestions('a')).resolves.toHaveLength(4);
    });

    it('resolves to nothing on an error response', async () => {
        stubFetch({ message: 'Forbidden' }, false);

        await expect(fetchLinkSuggestions('a')).resolves.toEqual([]);
    });

    it('resolves to nothing when the request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await expect(fetchLinkSuggestions('a')).resolves.toEqual([]);
    });
});

describe('editorSettings link search', () => {
    it('gives both editors the fetch function', () => {
        expect(
            (editorSettings as { __experimentalFetchLinkSuggestions?: unknown })
                .__experimentalFetchLinkSuggestions
        ).toBe(fetchLinkSuggestions);
    });
});
