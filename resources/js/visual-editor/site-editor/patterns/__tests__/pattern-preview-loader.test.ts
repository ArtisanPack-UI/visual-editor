import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PatternPreviewBatch } from '../api-client';
import {
    PATTERN_PREVIEW_BATCH_DELAY,
    PATTERN_PREVIEW_MAX_BATCH,
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
