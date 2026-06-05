import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApiError, fetchContent, saveContent } from '../api-client';

const CONFIG = {
    apiBase: '/visual-editor/api',
    resource: 'posts',
    id: '42',
} as const;

function mockFetchResponse(body: unknown, init: ResponseInit = { status: 200 }): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                new Response(JSON.stringify(body), {
                    status: init.status ?? 200,
                    headers: { 'content-type': 'application/json' },
                })
        )
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
    document.head.innerHTML = '';
});

describe('fetchContent', () => {
    it('GETs the content endpoint and returns the parsed JSON body', async () => {
        mockFetchResponse({
            id: 42,
            resource: 'posts',
            blocks: [],
            updated_at: null,
        });

        const response = await fetchContent(CONFIG);

        expect(response.id).toBe(42);
        expect(response.blocks).toEqual([]);
        expect(fetch).toHaveBeenCalledWith(
            '/visual-editor/api/posts/42/content',
            expect.objectContaining({ method: 'GET', credentials: 'same-origin' })
        );
    });

    it('throws ApiError with the parsed body on non-OK responses', async () => {
        mockFetchResponse({ message: 'Unauthorized' }, { status: 401 });

        await expect(fetchContent(CONFIG)).rejects.toMatchObject({
            status: 401,
            body: { message: 'Unauthorized' },
        });

        await expect(fetchContent(CONFIG)).rejects.toBeInstanceOf(ApiError);
    });
});

describe('saveContent', () => {
    it('PUTs a JSON body and forwards the CSRF token when present', async () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'token-123';
        document.head.appendChild(meta);

        mockFetchResponse({
            id: 42,
            resource: 'posts',
            blocks: [{ clientId: 'a', name: 'core/paragraph', attributes: {}, innerBlocks: [] }],
            updated_at: '2026-04-19T10:00:00Z',
        });

        const blocks = [
            { clientId: 'a', name: 'core/paragraph', attributes: {}, innerBlocks: [] },
        ];

        const response = await saveContent(CONFIG, blocks);

        expect(response.updated_at).toBe('2026-04-19T10:00:00Z');

        const [, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0];
        expect(init.method).toBe('PUT');
        expect(init.headers['X-CSRF-TOKEN']).toBe('token-123');
        expect(init.body).toBe(JSON.stringify({ blocks }));
    });

    it('still succeeds when no CSRF meta tag is present', async () => {
        mockFetchResponse({
            id: 42,
            resource: 'posts',
            blocks: [],
            updated_at: null,
        });

        const response = await saveContent(CONFIG, []);

        expect(response.id).toBe(42);

        const [, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0];
        expect(init.headers['X-CSRF-TOKEN']).toBeUndefined();
    });
});
