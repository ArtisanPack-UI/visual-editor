import { describe, expect, it, vi } from 'vitest';
import { fetchPost, PostRestError, savePost } from '../postRestClient';
import type { Block } from '../../store';

const samplePayload = {
    id: 1,
    title: 'Test Post',
    blocks: [
        {
            clientId: 'abc-123',
            name: 'core/paragraph',
            attributes: { content: 'Hello' },
            innerBlocks: [],
        },
    ],
    updated_at: '2026-04-14T12:34:56+00:00',
};

function mockJsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('fetchPost', () => {
    it('returns the parsed payload on success', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(mockJsonResponse(samplePayload));

        const result = await fetchPost('1', {
            apiBase: '/visual-editor/api',
            fetchImpl,
        });

        expect(result).toEqual(samplePayload);
        expect(fetchImpl).toHaveBeenCalledTimes(1);

        const [url, init] = fetchImpl.mock.calls[0];
        expect(url).toBe('/visual-editor/api/posts/1');
        expect(init.method).toBe('GET');
        expect((init.headers as Headers).get('Accept')).toBe('application/json');
        expect((init.headers as Headers).get('X-Requested-With')).toBe('XMLHttpRequest');
    });

    it('throws PostRestError on non-2xx status', async () => {
        const fetchImpl = vi
            .fn()
            .mockResolvedValue(mockJsonResponse({ message: 'Nope' }, 403));

        await expect(
            fetchPost('1', { apiBase: '/visual-editor/api', fetchImpl })
        ).rejects.toMatchObject({
            name: 'PostRestError',
            status: 403,
        });
    });

    it('throws PostRestError when the payload shape is wrong', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(
            mockJsonResponse({ id: 'not-a-number', title: 'x', blocks: [], updated_at: 'x' })
        );

        await expect(
            fetchPost('1', { apiBase: '/visual-editor/api', fetchImpl })
        ).rejects.toBeInstanceOf(PostRestError);
    });

    it('normalizes trailing slashes in apiBase', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(mockJsonResponse(samplePayload));

        await fetchPost('42', {
            apiBase: '/visual-editor/api/',
            fetchImpl,
        });

        expect(fetchImpl.mock.calls[0][0]).toBe('/visual-editor/api/posts/42');
    });

    it('attaches a CSRF token when provided', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(mockJsonResponse(samplePayload));

        await fetchPost('1', {
            apiBase: '/visual-editor/api',
            fetchImpl,
            csrfToken: 'token-xyz',
        });

        const headers = fetchImpl.mock.calls[0][1].headers as Headers;
        expect(headers.get('X-CSRF-TOKEN')).toBe('token-xyz');
    });
});

describe('savePost', () => {
    const blocks: Block[] = [
        {
            clientId: 'abc',
            name: 'core/paragraph',
            attributes: { content: 'Updated' },
            innerBlocks: [],
        },
    ];

    it('PUTs the block tree and returns the persisted payload', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(mockJsonResponse(samplePayload));

        const result = await savePost('1', blocks, {
            apiBase: '/visual-editor/api',
            fetchImpl,
        });

        expect(result).toEqual(samplePayload);

        const [url, init] = fetchImpl.mock.calls[0];
        expect(url).toBe('/visual-editor/api/posts/1');
        expect(init.method).toBe('PUT');
        expect((init.headers as Headers).get('Content-Type')).toBe('application/json');
        expect(init.body).toBe(JSON.stringify({ blocks }));
    });

    it('throws PostRestError on validation failure', async () => {
        const fetchImpl = vi.fn().mockResolvedValue(
            mockJsonResponse({ message: 'The blocks.0.name field is required.' }, 422)
        );

        await expect(
            savePost('1', blocks, { apiBase: '/visual-editor/api', fetchImpl })
        ).rejects.toMatchObject({
            name: 'PostRestError',
            status: 422,
        });
    });
});
