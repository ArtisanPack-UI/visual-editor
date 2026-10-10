import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { SiteEditorApiError, type PatternPreviewBatch } from '../api-client';
import {
    PATTERN_PREVIEW_BATCH_DELAY,
    PATTERN_PREVIEW_CACHE_LIMIT,
    PATTERN_PREVIEW_MAX_BATCH,
    PATTERN_PREVIEW_RATE_LIMIT_COOLDOWN,
    PATTERN_PREVIEW_TIMEOUT,
    PatternPreviewLoader,
    patternContentSignature,
} from '../pattern-preview-loader';

function batchFor(ids: readonly string[]): PatternPreviewBatch {
    return {
        styles: '<style>shared</style>',
        patterns: Object.fromEntries(
            ids.map((id) => [id, { html: `<p>${id}</p>` }])
        ),
    };
}

describe('PatternPreviewLoader', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('batches loads made within the window into one request', async () => {
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        const first = loader.load('hero', 'a');
        const second = loader.load('12', 'b');

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(fetcher).toHaveBeenCalledTimes(1);
        expect(fetcher.mock.calls[0]?.[1]).toEqual(['hero', '12']);
        await expect(first).resolves.toEqual({
            html: '<p>hero</p>',
            styles: '<style>shared</style>',
        });
        await expect(second).resolves.toEqual({
            html: '<p>12</p>',
            styles: '<style>shared</style>',
        });
    });

    it('splits a large queue at the server batch cap', async () => {
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        for (let i = 0; i < PATTERN_PREVIEW_MAX_BATCH + 3; i++) {
            void loader.load(`p${i}`, 's');
        }

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(fetcher).toHaveBeenCalledTimes(2);
        expect(fetcher.mock.calls[0]?.[1]).toHaveLength(PATTERN_PREVIEW_MAX_BATCH);
        expect(fetcher.mock.calls[1]?.[1]).toHaveLength(3);
    });

    it('memoizes a pattern until its content signature changes', async () => {
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        void loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        void loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(fetcher).toHaveBeenCalledTimes(1);

        void loader.load('hero', 'b');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(fetcher).toHaveBeenCalledTimes(2);
    });

    it('resolves a per-pattern error to null without failing the batch', async () => {
        const fetcher = vi.fn(async () => ({
            styles: '',
            patterns: {
                hero: { html: '<p>hero</p>' },
                broken: { error: 'render_failed' },
            },
        }));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        const hero = loader.load('hero', 'a');
        const broken = loader.load('broken', 'a');

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(hero).resolves.toMatchObject({ html: '<p>hero</p>' });
        await expect(broken).resolves.toBeNull();
    });

    it('resolves every card to null on a network error and retries later', async () => {
        const fetcher = vi
            .fn()
            .mockRejectedValueOnce(new Error('403'))
            .mockImplementation(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        const first = loader.load('hero', 'a');
        const second = loader.load('12', 'a');

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(first).resolves.toBeNull();
        await expect(second).resolves.toBeNull();

        const retry = loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(retry).resolves.toMatchObject({ html: '<p>hero</p>' });
        expect(fetcher).toHaveBeenCalledTimes(2);
    });

    it('serves every signature queued for the same id from one request', async () => {
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        const first = loader.load('hero', 'a');
        const second = loader.load('hero', 'b');

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(fetcher).toHaveBeenCalledTimes(1);
        expect(fetcher.mock.calls[0]?.[1]).toEqual(['hero']);
        await expect(first).resolves.not.toBeNull();
        await expect(second).resolves.not.toBeNull();
    });
});

describe('PatternPreviewLoader caching and concurrency (FE-4)', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('stops fetching after a 403', async () => {
        const fetcher = vi
            .fn()
            .mockRejectedValue(new SiteEditorApiError('Forbidden', 403, null));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        const first = loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);
        await expect(first).resolves.toBeNull();

        const again = loader.load('hero', 'a');
        const other = loader.load('cta', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(again).resolves.toBeNull();
        await expect(other).resolves.toBeNull();
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('stops fetching once the server reports renderer_unavailable', async () => {
        const fetcher = vi.fn(async () => ({
            styles: '',
            patterns: { hero: { error: 'renderer_unavailable' } },
        }));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        void loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(loader.load('hero', 'a')).resolves.toBeNull();
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);
        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it('pauses after a 429 and resumes once the cooldown passes', async () => {
        const fetcher = vi
            .fn()
            .mockRejectedValueOnce(new SiteEditorApiError('Too many', 429, null))
            .mockImplementation(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        void loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(loader.load('hero', 'a')).resolves.toBeNull();
        expect(fetcher).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_RATE_LIMIT_COOLDOWN);

        const retry = loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        await expect(retry).resolves.toMatchObject({ html: '<p>hero</p>' });
        expect(fetcher).toHaveBeenCalledTimes(2);
    });

    it('evicts superseded signatures when a new one renders', async () => {
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        void loader.load('hero', 'a');
        void loader.load('cta', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        void loader.load('hero', 'b');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(loader.has('hero', 'a')).toBe(false);
        expect(loader.has('hero', 'b')).toBe(true);
        expect(loader.has('cta', 'a')).toBe(true);
    });

    it('caps the memo, evicting the least recently used entry', async () => {
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => batchFor(ids));
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        for (let i = 0; i < PATTERN_PREVIEW_CACHE_LIMIT; i++) {
            void loader.load(`p${i}`, 's');
        }

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        // Touch p0 so p1 becomes the oldest.
        void loader.load('p0', 's');
        void loader.load('extra', 's');

        expect(loader.size).toBe(PATTERN_PREVIEW_CACHE_LIMIT);
        expect(loader.has('p0', 's')).toBe(true);
        expect(loader.has('p1', 's')).toBe(false);
        expect(loader.has('extra', 's')).toBe(true);
    });

    it('keeps two chunks in flight concurrently', async () => {
        let inFlight = 0;
        let peak = 0;
        const fetcher = vi.fn(async (_config, ids: readonly string[]) => {
            inFlight++;
            peak = Math.max(peak, inFlight);
            await new Promise((resolve) => setTimeout(resolve, 100));
            inFlight--;

            return batchFor(ids);
        });
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        for (let i = 0; i < PATTERN_PREVIEW_MAX_BATCH * 3; i++) {
            void loader.load(`p${i}`, 's');
        }

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(fetcher).toHaveBeenCalledTimes(2);

        await vi.advanceTimersByTimeAsync(300);

        expect(fetcher).toHaveBeenCalledTimes(3);
        expect(peak).toBe(2);
    });

    it('aborts a request that outlives the timeout and resolves its cards to null', async () => {
        let receivedSignal: AbortSignal | undefined;
        const fetcher = vi.fn(
            (_config, _ids: readonly string[], options?: { signal?: AbortSignal }) =>
                new Promise<PatternPreviewBatch>((_resolve, reject) => {
                    receivedSignal = options?.signal;
                    options?.signal?.addEventListener('abort', () =>
                        reject(new Error('aborted'))
                    );
                })
        );
        const loader = new PatternPreviewLoader({ apiBase: '/api' }, fetcher);

        const pending = loader.load('hero', 'a');
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);

        expect(receivedSignal?.aborted).toBe(false);

        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_TIMEOUT);

        expect(receivedSignal?.aborted).toBe(true);
        await expect(pending).resolves.toBeNull();
        expect(loader.has('hero', 'a')).toBe(false);
    });
});

describe('patternContentSignature', () => {
    it('is stable for the same content and changes with it', () => {
        const blocks = [{ name: 'core/paragraph' }];

        expect(patternContentSignature('<p>a</p>', blocks)).toBe(
            patternContentSignature('<p>a</p>', blocks)
        );
        expect(patternContentSignature('<p>a</p>', blocks)).not.toBe(
            patternContentSignature('<p>b</p>', blocks)
        );
        expect(patternContentSignature('', blocks)).not.toBe(
            patternContentSignature('', [{ name: 'core/heading' }])
        );
    });
});
