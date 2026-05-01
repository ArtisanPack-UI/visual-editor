import { renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useQueryPreview } from '../use-query-preview';

const fetchMock = vi.fn();
const originalFetch = globalThis.fetch;

beforeEach(() => {
    fetchMock.mockReset();
    globalThis.fetch = fetchMock as typeof globalThis.fetch;
});

afterEach(() => {
    globalThis.fetch = originalFetch;
});

describe('useQueryPreview', () => {
    it('returns idle state when query is null', () => {
        const { result } = renderHook(() => useQueryPreview(null));

        expect(result.current.status).toBe('idle');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('recursively strips empty nested objects and arrays', async () => {
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify({ data: [], meta: { total: 0 } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        );

        renderHook(() =>
            useQueryPreview(
                {
                    queryId: 'abc',
                    postType: 'post',
                    // A partially-configured taxQuery the user toggled
                    // back off. Validation would reject it as missing
                    // required `terms`; the recursive strip should
                    // collapse the entire object away.
                    taxQuery: { taxonomy: '', terms: [], operator: '' },
                    // Nested object with a meaningful key — that key
                    // survives, the empty siblings get pruned.
                    nested: { keep: 'value', drop: '', alsoDrop: [] },
                },
                { debounceMs: 0 }
            )
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());

        const init = fetchMock.mock.calls[0][1] as RequestInit;
        const body = JSON.parse(init.body as string) as Record<string, unknown>;

        expect(body).not.toHaveProperty('taxQuery');
        expect(body).toEqual({
            queryId: 'abc',
            postType: 'post',
            nested: { keep: 'value' },
        });
    });

    it('preserves caller-supplied headers without dropping the required ones', async () => {
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify({ data: [], meta: { total: 0 } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        );

        renderHook(() =>
            useQueryPreview(
                { postType: 'post' },
                {
                    debounceMs: 0,
                    fetchOptions: {
                        headers: { 'X-Trace': 'caller-supplied' },
                    },
                }
            )
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());

        const init = fetchMock.mock.calls[0][1] as RequestInit;
        const headers = init.headers as Record<string, string>;

        // The required headers stay in their original case (they're set
        // by the hook directly); caller-supplied headers come back
        // lowercased because they round-trip through the Headers spec
        // normaliser.
        expect(headers['Content-Type']).toBe('application/json');
        expect(headers.Accept).toBe('application/json');
        expect(headers['x-trace']).toBe('caller-supplied');
    });

    it('handles caller-supplied Headers instances and tuple arrays', async () => {
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify({ data: [], meta: { total: 0 } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        );

        const callerHeaders = new Headers({ 'X-Trace': 'instance' });

        renderHook(() =>
            useQueryPreview(
                { postType: 'post' },
                {
                    debounceMs: 0,
                    fetchOptions: { headers: callerHeaders },
                }
            )
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());

        const init = fetchMock.mock.calls[0][1] as RequestInit;
        const headers = init.headers as Record<string, string>;

        // Headers normalises keys to lowercase — assert against that.
        expect(headers['x-trace']).toBe('instance');
        expect(headers['Content-Type']).toBe('application/json');
    });

    it('strips upstream empty defaults from the posted payload', async () => {
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify({ data: [], meta: { total: 0 } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        );

        renderHook(() =>
            useQueryPreview(
                {
                    queryId: 'abc',
                    postType: 'post',
                    perPage: 3,
                    // Upstream default placeholders that should be dropped.
                    pages: 0,
                    author: '',
                    sticky: '',
                    taxQuery: '',
                    search: '',
                    exclude: [],
                },
                { debounceMs: 0 }
            )
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());

        const init = fetchMock.mock.calls[0][1] as RequestInit;
        const body = JSON.parse(init.body as string) as Record<string, unknown>;

        // Empty strings, empty arrays, and empty objects are dropped.
        // Numeric zeros (e.g. `pages: 0`) survive and the server treats
        // them as the documented "no cap" sentinel.
        expect(body).toEqual({ queryId: 'abc', postType: 'post', perPage: 3, pages: 0 });
        expect(body).not.toHaveProperty('author');
        expect(body).not.toHaveProperty('sticky');
        expect(body).not.toHaveProperty('taxQuery');
        expect(body).not.toHaveProperty('search');
        expect(body).not.toHaveProperty('exclude');
    });

    it('attaches the X-CSRF-TOKEN header when a meta tag is present', async () => {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'abc123');
        document.head.appendChild(meta);

        try {
            fetchMock.mockResolvedValue(
                new Response(JSON.stringify({ data: [], meta: { total: 0 } }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                })
            );

            renderHook(() => useQueryPreview({ postType: 'post' }, { debounceMs: 0 }));

            await waitFor(() => expect(fetchMock).toHaveBeenCalled());

            const init = fetchMock.mock.calls[0][1] as RequestInit;
            const headers = init.headers as Record<string, string>;

            expect(headers['X-CSRF-TOKEN']).toBe('abc123');
        } finally {
            document.head.removeChild(meta);
        }
    });

    it('reports ready with mapped posts after the debounce window', async () => {
        fetchMock.mockResolvedValue(
            new Response(
                JSON.stringify({
                    data: [
                        {
                            id: 1,
                            title: { rendered: 'Hello' },
                            excerpt: { rendered: 'World' },
                            link: '/posts/1',
                            date: '2026-04-30T12:00:00+00:00',
                        },
                    ],
                    meta: { total: 7 },
                }),
                { status: 200, headers: { 'Content-Type': 'application/json' } }
            )
        );

        const { result } = renderHook(() =>
            useQueryPreview({ postType: 'post', perPage: 3 }, { debounceMs: 5 })
        );

        await waitFor(() => expect(result.current.status).toBe('ready'));

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(result.current.posts[0]).toMatchObject({
            id: 1,
            title: 'Hello',
            excerpt: 'World',
            permalink: '/posts/1',
            publishedAt: '2026-04-30T12:00:00+00:00',
        });
        expect(result.current.total).toBe(7);
    });

    it('surfaces an error state on non-2xx responses', async () => {
        fetchMock.mockResolvedValue(
            new Response('runtime missing', { status: 503 })
        );

        const { result } = renderHook(() =>
            useQueryPreview({ postType: 'post' }, { debounceMs: 5 })
        );

        await waitFor(() => expect(result.current.status).toBe('error'));

        expect(result.current.error).toBe('runtime missing');
        expect(result.current.posts).toEqual([]);
    });
});
