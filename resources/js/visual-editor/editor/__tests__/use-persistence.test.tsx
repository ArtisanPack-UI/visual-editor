import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { usePersistence } from '../use-persistence';

const CONFIG = {
    apiBase: '/visual-editor/api',
    resource: 'posts',
    id: '42',
    debounceMs: 20,
};

interface RecordedCall {
    url: string;
    method: string;
    init: RequestInit | undefined;
}

type MockResponseFactory = (call: RecordedCall) => Response | Promise<Response>;

function mockFetch(factory: MockResponseFactory): ReturnType<typeof vi.fn> {
    const mock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' || input instanceof URL
            ? String(input)
            : input.url;
        const method = (init?.method ?? 'GET').toUpperCase();
        return factory({ url, method, init });
    });
    vi.stubGlobal('fetch', mock);
    return mock;
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'content-type': 'application/json' },
    });
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('usePersistence', () => {
    it('loads initial content and marks the hook ready', async () => {
        mockFetch(() =>
            jsonResponse({
                id: 42,
                resource: 'posts',
                blocks: [
                    { clientId: 'a', name: 'core/paragraph', attributes: {}, innerBlocks: [] },
                ],
                updated_at: '2026-04-19T10:00:00Z',
            })
        );

        const { result } = renderHook(() => usePersistence(CONFIG));

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('ready');
        });

        expect(result.current.blocks).toHaveLength(1);
        expect(result.current.lastSavedAt).toBe('2026-04-19T10:00:00Z');
    });

    it('exposes load errors so the editor can recover', async () => {
        mockFetch(() => jsonResponse({ message: 'forbidden' }, 403));

        const { result } = renderHook(() => usePersistence(CONFIG));

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('error');
        });

        expect(result.current.loadError?.status).toBe(403);
    });

    it('debounces rapid edits into a single PUT and surfaces the saved timestamp', async () => {
        const requests: RecordedCall[] = [];
        mockFetch((call) => {
            requests.push(call);
            if (call.method === 'GET') {
                return jsonResponse({ id: 42, resource: 'posts', blocks: [], updated_at: null });
            }
            return jsonResponse({
                id: 42,
                resource: 'posts',
                blocks: [],
                updated_at: '2026-04-19T10:10:00Z',
            });
        });

        const { result } = renderHook(() => usePersistence(CONFIG));

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('ready');
        });

        act(() => {
            result.current.onBlocksChange([
                { clientId: '1', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
            ]);
            result.current.onBlocksChange([
                { clientId: '2', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
            ]);
            result.current.onBlocksChange([
                { clientId: '3', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
            ]);
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        const putRequests = requests.filter((r) => r.method === 'PUT');
        expect(putRequests).toHaveLength(1);
        expect(putRequests[0]?.init?.body).toContain('"clientId":"3"');
        expect(result.current.lastSavedAt).toBe('2026-04-19T10:10:00Z');
    });

    it('reports save errors without clobbering loaded content', async () => {
        mockFetch((call) => {
            if (call.method === 'GET') {
                return jsonResponse({ id: 42, resource: 'posts', blocks: [], updated_at: null });
            }
            return jsonResponse({ message: 'validation failed' }, 422);
        });

        const { result } = renderHook(() => usePersistence(CONFIG));

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('ready');
        });

        act(() => {
            result.current.onBlocksChange([
                { clientId: 'x', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
            ]);
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('error');
        });

        expect(result.current.saveError?.status).toBe(422);
        expect(result.current.blocks).toHaveLength(1);
    });

    it('flushes pending edits immediately when flush() is called', async () => {
        const requests: RecordedCall[] = [];
        mockFetch((call) => {
            requests.push(call);
            if (call.method === 'GET') {
                return jsonResponse({ id: 42, resource: 'posts', blocks: [], updated_at: null });
            }
            return jsonResponse({
                id: 42,
                resource: 'posts',
                blocks: [],
                updated_at: '2026-04-19T10:20:00Z',
            });
        });

        const { result } = renderHook(() =>
            usePersistence({ ...CONFIG, debounceMs: 5000 })
        );

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('ready');
        });

        act(() => {
            result.current.onBlocksChange([
                { clientId: 'flush', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
            ]);
        });

        expect(requests.filter((r) => r.method === 'PUT')).toHaveLength(0);

        act(() => {
            result.current.flush();
        });

        await waitFor(() => {
            expect(result.current.saveStatus).toBe('saved');
        });

        expect(requests.filter((r) => r.method === 'PUT')).toHaveLength(1);
    });

    it('flush() is a no-op when there is no pending change', async () => {
        const requests: RecordedCall[] = [];
        mockFetch((call) => {
            requests.push(call);
            return jsonResponse({ id: 42, resource: 'posts', blocks: [], updated_at: null });
        });

        const { result } = renderHook(() => usePersistence(CONFIG));

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('ready');
        });

        act(() => {
            result.current.flush();
        });

        await new Promise((resolve) => setTimeout(resolve, CONFIG.debounceMs + 20));

        expect(requests.filter((r) => r.method === 'PUT')).toHaveLength(0);
    });

    it('does not fire PUTs for edits before the initial load finishes', async () => {
        let resolveLoad: (value: Response) => void = () => {};
        const loadPromise = new Promise<Response>((resolve) => {
            resolveLoad = resolve;
        });

        const mock = mockFetch((call) => {
            if (call.method === 'GET') {
                return loadPromise;
            }
            return jsonResponse({ id: 42, resource: 'posts', blocks: [], updated_at: null });
        });

        const { result } = renderHook(() => usePersistence(CONFIG));

        act(() => {
            result.current.onBlocksChange([
                { clientId: 'early', name: 'core/paragraph', attributes: {}, innerBlocks: [] } as never,
            ]);
        });

        // Let the debounce timer expire while load is still pending.
        await new Promise((resolve) => setTimeout(resolve, CONFIG.debounceMs + 20));

        expect(
            mock.mock.calls.filter(([, init]) => (init as RequestInit | undefined)?.method === 'PUT')
        ).toHaveLength(0);

        act(() => {
            resolveLoad(
                jsonResponse({ id: 42, resource: 'posts', blocks: [], updated_at: null })
            );
        });

        await waitFor(() => {
            expect(result.current.loadStatus).toBe('ready');
        });
    });
});
