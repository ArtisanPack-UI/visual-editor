/**
 * Navigation REST client tests.
 *
 * Mirrors `../../__tests__/api-client.test.ts` patterns: stub
 * `fetch`, assert on URL / method / body for each helper, and verify
 * shape filtering for the locations + search endpoints.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    createNavigation,
    fetchMenuLocations,
    fetchNavigation,
    listNavigations,
    searchEntities,
    updateNavigation,
} from '../api-client';

const API_BASE = '/visual-editor/api';

function mockFetch(
    response: Response | (() => Response)
): ReturnType<typeof vi.fn> {
    const fn = vi.fn(async (): Promise<Response> =>
        typeof response === 'function' ? response() : response
    );

    vi.stubGlobal('fetch', fn);

    return fn;
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    document.querySelectorAll('meta[name="csrf-token"]').forEach((node) => {
        node.remove();
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('listNavigations', () => {
    it('returns the paginated envelope', async () => {
        const fetchMock = mockFetch(
            jsonResponse({
                data: [
                    {
                        id: 5,
                        slug: 'primary',
                        title: { rendered: 'Primary' },
                        content: { raw: '', blocks: [] },
                        status: 'publish',
                        menu_order: 0,
                        location: 'primary',
                        type: 'wp_navigation',
                    },
                ],
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 25,
                    total: 1,
                },
            })
        );

        const response = await listNavigations(
            { apiBase: API_BASE },
            { perPage: 25 }
        );

        expect(response.data).toHaveLength(1);
        expect(response.meta.total).toBe(1);
        expect(fetchMock.mock.calls[0]?.[0]).toContain(
            '/visual-editor/api/navigation?per_page=25'
        );
    });
});

describe('fetchNavigation', () => {
    it('GETs a single record by id', async () => {
        const fetchMock = mockFetch(
            jsonResponse({
                id: 9,
                slug: 'footer',
                title: { rendered: 'Footer' },
                content: { raw: '', blocks: [] },
                status: 'publish',
                menu_order: 0,
                location: null,
                type: 'wp_navigation',
            })
        );

        const record = await fetchNavigation({ apiBase: API_BASE }, 9);

        expect(record.slug).toBe('footer');
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/visual-editor/api/navigation/9'
        );
    });
});

describe('createNavigation + updateNavigation', () => {
    it('POSTs a JSON body for create', async () => {
        const fetchMock = mockFetch(
            jsonResponse(
                {
                    id: 1,
                    slug: 'primary',
                    title: { rendered: 'Primary' },
                    content: { raw: '', blocks: [] },
                    status: 'publish',
                    menu_order: 0,
                    location: null,
                    type: 'wp_navigation',
                },
                201
            )
        );

        await createNavigation(
            { apiBase: API_BASE },
            { slug: 'primary', title: 'Primary' }
        );

        expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({
            method: 'POST',
        });
        const body = JSON.parse(
            (fetchMock.mock.calls[0]?.[1] as { body: string }).body
        );
        expect(body).toEqual({ slug: 'primary', title: 'Primary' });
    });

    it('PUTs the update payload at /navigation/{id}', async () => {
        const fetchMock = mockFetch(
            jsonResponse({
                id: 2,
                slug: 'primary',
                title: { rendered: 'Renamed' },
                content: { raw: '', blocks: [] },
                status: 'publish',
                menu_order: 0,
                location: 'primary',
                type: 'wp_navigation',
            })
        );

        await updateNavigation(
            { apiBase: API_BASE },
            2,
            { title: 'Renamed', location: 'primary' }
        );

        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/visual-editor/api/navigation/2'
        );
        expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({
            method: 'PUT',
        });
    });
});

describe('fetchMenuLocations', () => {
    it('returns the configured locations', async () => {
        mockFetch(
            jsonResponse({
                data: [
                    {
                        slug: 'primary',
                        label: 'Primary Menu',
                        menu: { id: 1, slug: 'main', title: 'Main' },
                        is_fallback: false,
                    },
                    {
                        slug: 'footer',
                        label: 'Footer',
                        menu: null,
                        is_fallback: false,
                    },
                ],
            })
        );

        const result = await fetchMenuLocations({ apiBase: API_BASE });

        expect(result).toHaveLength(2);
        expect(result[0].slug).toBe('primary');
        expect(result[1].menu).toBeNull();
    });

    it('drops malformed rows', async () => {
        mockFetch(
            jsonResponse({
                data: [
                    { slug: 'primary', label: 'Primary', is_fallback: false },
                    { slug: 'broken' /* missing label */ },
                    null,
                ],
            })
        );

        const result = await fetchMenuLocations({ apiBase: API_BASE });

        expect(result).toHaveLength(1);
        expect(result[0].slug).toBe('primary');
    });
});

describe('searchEntities', () => {
    it('passes the type + q params', async () => {
        const fetchMock = mockFetch(
            jsonResponse({
                data: [
                    { type: 'page', id: 1, title: 'About', url: '/about' },
                ],
            })
        );

        const results = await searchEntities(
            { apiBase: API_BASE },
            { type: 'page', q: 'about' }
        );

        expect(results).toHaveLength(1);
        expect(fetchMock.mock.calls[0]?.[0]).toContain(
            '/visual-editor/api/search?'
        );
        expect(fetchMock.mock.calls[0]?.[0]).toContain('type=page');
        expect(fetchMock.mock.calls[0]?.[0]).toContain('q=about');
    });

    it('drops rows that are not search-result-shaped', async () => {
        mockFetch(
            jsonResponse({
                data: [
                    { type: 'page', id: 1, title: 'OK' },
                    { id: 2 },
                    null,
                ],
            })
        );

        const results = await searchEntities(
            { apiBase: API_BASE },
            { type: 'page', q: '' }
        );

        expect(results).toHaveLength(1);
    });
});
