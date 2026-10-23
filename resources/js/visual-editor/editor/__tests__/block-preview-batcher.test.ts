import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    MAX_PREVIEW_BATCH_ITEMS,
    PREVIEW_BATCH_WINDOW_MS,
    clearBlockPreviewCache,
    getCachedBlockPreview,
    queueBlockPreview,
} from '../block-preview-batcher';
import { ApiError } from '../api-client';

const API_BASE = '/visual-editor/api';

interface BatchItem {
    key: string;
    name: string;
    attributes?: Record<string, unknown>;
}

/** Fetch mock that renders every item as `<p>{name}:{attributes}</p>`. */
function installRenderingFetch(
    overrides: (item: BatchItem) => Record<string, unknown> | null = () => null
): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn(async (_url: string, init: RequestInit = {}) => {
        const { items } = JSON.parse(init.body as string) as { items: BatchItem[] };

        return new Response(
            JSON.stringify({
                results: items.map(
                    (item) =>
                        overrides(item) ?? {
                            key: item.key,
                            name: item.name,
                            html: `<p>${item.name}:${JSON.stringify(item.attributes ?? {})}</p>`,
                        }
                ),
            }),
            { status: 200, headers: { 'content-type': 'application/json' } }
        );
    });

    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

function sentItems(fetchMock: ReturnType<typeof vi.fn>, call = 0): BatchItem[] {
    return JSON.parse((fetchMock.mock.calls[call][1] as RequestInit).body as string).items;
}

describe('block preview batcher', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        clearBlockPreviewCache();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('sends identical payloads as one item and shares the result', async () => {
        const fetchMock = installRenderingFetch();
        const payload = { name: 'acme/copy', attributes: { command: 'npm i' } };

        const results = Promise.all(
            Array.from({ length: 13 }, () => queueBlockPreview(API_BASE, payload))
        );

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(sentItems(fetchMock)).toHaveLength(1);
        expect(new Set(await results).size).toBe(1);
    });

    it('sends distinct payloads in one request and routes each result back', async () => {
        const fetchMock = installRenderingFetch();

        const a = queueBlockPreview(API_BASE, { name: 'acme/a' });
        const b = queueBlockPreview(API_BASE, { name: 'acme/b', attributes: { x: 1 } });

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(sentItems(fetchMock).map((item) => item.name)).toEqual(['acme/a', 'acme/b']);
        await expect(a).resolves.toBe('<p>acme/a:{}</p>');
        await expect(b).resolves.toBe('<p>acme/b:{"x":1}</p>');
    });

    it('splits a large flush into capped chunks', async () => {
        const fetchMock = installRenderingFetch();

        const all = Promise.all(
            Array.from({ length: MAX_PREVIEW_BATCH_ITEMS + 5 }, (_, index) =>
                queueBlockPreview(API_BASE, { name: 'acme/n', attributes: { index } })
            )
        );

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);
        await all;

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(sentItems(fetchMock, 0)).toHaveLength(MAX_PREVIEW_BATCH_ITEMS);
        expect(sentItems(fetchMock, 1)).toHaveLength(5);
    });

    it('caches results for the session so a repeat payload does not refetch', async () => {
        const fetchMock = installRenderingFetch();
        const payload = { name: 'acme/a', attributes: { y: 2 } };

        const first = queueBlockPreview(API_BASE, payload);
        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);
        await first;

        expect(getCachedBlockPreview(API_BASE, payload)).toBe('<p>acme/a:{"y":2}</p>');
        await expect(queueBlockPreview(API_BASE, payload)).resolves.toBe('<p>acme/a:{"y":2}</p>');

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('rejects only the failing item and does not cache the failure', async () => {
        const fetchMock = installRenderingFetch((item) =>
            item.name === 'acme/missing'
                ? { key: item.key, error: 'block_not_registered', status: 404 }
                : null
        );

        const missing = queueBlockPreview(API_BASE, { name: 'acme/missing' });
        const ok = queueBlockPreview(API_BASE, { name: 'acme/ok' });
        const missingOutcome = missing.catch((error: unknown) => error);

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);

        const error = await missingOutcome;
        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).status).toBe(404);
        await expect(ok).resolves.toBe('<p>acme/ok:{}</p>');
        expect(getCachedBlockPreview(API_BASE, { name: 'acme/missing' })).toBeUndefined();

        const retry = queueBlockPreview(API_BASE, { name: 'acme/missing' }).catch(() => null);
        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);
        await retry;

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('rejects every item in a chunk when the request fails', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response('{}', { status: 503 }))
        );

        const outcomes = Promise.all([
            queueBlockPreview(API_BASE, { name: 'acme/a' }).catch((error: unknown) => error),
            queueBlockPreview(API_BASE, { name: 'acme/b' }).catch((error: unknown) => error),
        ]);

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);

        for (const error of await outcomes) {
            expect((error as ApiError).status).toBe(503);
        }
    });

    it('drops an aborted payload from the queue before it is sent', async () => {
        const fetchMock = installRenderingFetch();
        const controller = new AbortController();

        const aborted = queueBlockPreview(API_BASE, { name: 'acme/gone' }, controller.signal);
        const kept = queueBlockPreview(API_BASE, { name: 'acme/kept' });

        controller.abort();

        await expect(aborted).rejects.toMatchObject({ name: 'AbortError' });

        await vi.advanceTimersByTimeAsync(PREVIEW_BATCH_WINDOW_MS);
        await kept;

        expect(sentItems(fetchMock).map((item) => item.name)).toEqual(['acme/kept']);
    });
});
