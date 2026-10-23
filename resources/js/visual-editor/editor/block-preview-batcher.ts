/**
 * Session-wide batcher for the `blocks/preview` endpoint (#1010).
 *
 * Every `<ServerSideRender>` used to POST its own request, so inserting
 * a pattern with many server-rendered blocks sent one request per block
 * at the same moment, each a full app boot plus a Blade render. Blocks
 * now queue their payload here instead; payloads queued within
 * {@link PREVIEW_BATCH_WINDOW_MS} of each other go out as one batch
 * request (split into chunks of {@link MAX_PREVIEW_BATCH_ITEMS}, sent
 * {@link PREVIEW_BATCH_CONCURRENCY} at a time) and each block's HTML is
 * routed back to it.
 *
 * Rendered HTML is cached by payload for the editor session, so
 * re-renders, remounts, undo/redo and identical blocks don't refetch.
 * Identical payloads that are queued or in flight share one entry.
 * Failures are not cached so they can be retried.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.17.0
 */

import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import {
    ApiError,
    previewBlocksBatch,
    type PreviewBlocksBatchItem,
} from './api-client';

/**
 * How long the queue collects blocks after the first one arrives before
 * it flushes, in ms.
 *
 * @since 1.17.0
 */
export const PREVIEW_BATCH_WINDOW_MS = 50;

/**
 * Most items sent in one request; mirrors `BlockPreviewRequest::MAX_ITEMS`.
 *
 * @since 1.17.0
 */
export const MAX_PREVIEW_BATCH_ITEMS = 25;

/**
 * Batch requests in flight at once when a flush spans several chunks.
 *
 * @since 1.17.0
 */
export const PREVIEW_BATCH_CONCURRENCY = 2;

const MAX_CACHE_ENTRIES = 200;

/** The single-block preview payload, without the batch `key`. */
export type BlockPreviewPayload = Omit<PreviewBlocksBatchItem, 'key'>;

interface Waiter {
    resolve: (html: string) => void;
    reject: (error: Error) => void;
}

interface Entry {
    apiBase: string;
    payload: BlockPreviewPayload;
    waiters: Set<Waiter>;
}

const cache = new Map<string, string>();
const queued = new Map<string, Entry>();
const inFlight = new Map<string, Entry>();
let flushTimer: ReturnType<typeof setTimeout> | null = null;

function hashPayload(apiBase: string, payload: BlockPreviewPayload): string {
    return JSON.stringify([
        apiBase,
        payload.name,
        payload.attributes ?? {},
        payload.innerBlocks ?? [],
        payload.bindings ?? {},
        payload.context ?? {},
    ]);
}

function remember(hash: string, html: string): void {
    cache.delete(hash);
    cache.set(hash, html);

    if (cache.size > MAX_CACHE_ENTRIES) {
        const oldest = cache.keys().next().value;

        if (oldest !== undefined) {
            cache.delete(oldest);
        }
    }
}

/** Read a cached render, refreshing its position for LRU eviction. */
function recall(hash: string): string | undefined {
    const html = cache.get(hash);

    if (html !== undefined) {
        cache.delete(hash);
        cache.set(hash, html);
    }

    return html;
}

function abortError(): Error {
    const error = new Error('The block preview was aborted.');
    error.name = 'AbortError';

    return error;
}

function itemError(status: number, body: unknown): ApiError {
    return new ApiError(
        sprintf(
            /* translators: %d: HTTP status code. */
            __('Visual editor request failed with status %d', TEXT_DOMAIN),
            status
        ),
        status,
        body
    );
}

function settle(entry: Entry, outcome: string | Error): void {
    for (const waiter of entry.waiters) {
        if (outcome instanceof Error) {
            waiter.reject(outcome);
        } else {
            waiter.resolve(outcome);
        }
    }

    entry.waiters.clear();
}

async function sendChunk(
    apiBase: string,
    chunk: ReadonlyArray<readonly [string, Entry]>
): Promise<void> {
    const items = chunk.map(([, entry], index) => ({
        key: String(index),
        ...entry.payload,
    }));

    try {
        const results = await previewBlocksBatch({ apiBase }, items);
        const byKey = new Map(results.map((result) => [String(result.key), result]));

        chunk.forEach(([hash, entry], index) => {
            inFlight.delete(hash);

            const result = byKey.get(String(index));

            if (result !== undefined && result.error === undefined && typeof result.html === 'string') {
                remember(hash, result.html);
                settle(entry, result.html);

                return;
            }

            settle(entry, itemError(result?.status ?? 0, result ?? null));
        });
    } catch (error: unknown) {
        const failure =
            error instanceof Error
                ? error
                : new ApiError(__('Failed to preview block.', TEXT_DOMAIN), 0, error);

        for (const [hash, entry] of chunk) {
            inFlight.delete(hash);
            settle(entry, failure);
        }
    }
}

async function sendAll(
    apiBase: string,
    entries: ReadonlyArray<readonly [string, Entry]>
): Promise<void> {
    const chunks: Array<ReadonlyArray<readonly [string, Entry]>> = [];

    for (let start = 0; start < entries.length; start += MAX_PREVIEW_BATCH_ITEMS) {
        chunks.push(entries.slice(start, start + MAX_PREVIEW_BATCH_ITEMS));
    }

    let next = 0;
    const worker = async (): Promise<void> => {
        while (next < chunks.length) {
            const chunk = chunks[next++];

            if (chunk !== undefined) {
                await sendChunk(apiBase, chunk);
            }
        }
    };

    await Promise.all(
        Array.from({ length: Math.min(PREVIEW_BATCH_CONCURRENCY, chunks.length) }, worker)
    );
}

function flush(): void {
    flushTimer = null;

    const byApiBase = new Map<string, Array<readonly [string, Entry]>>();

    for (const [hash, entry] of queued) {
        inFlight.set(hash, entry);

        const group = byApiBase.get(entry.apiBase) ?? [];
        group.push([hash, entry]);
        byApiBase.set(entry.apiBase, group);
    }

    queued.clear();

    for (const [apiBase, entries] of byApiBase) {
        void sendAll(apiBase, entries);
    }
}

function scheduleFlush(): void {
    if (flushTimer === null) {
        flushTimer = setTimeout(flush, PREVIEW_BATCH_WINDOW_MS);
    }
}

/**
 * The cached HTML for a payload this session has already rendered, or
 * `undefined` when it hasn't.
 *
 * @since 1.17.0
 */
export function getCachedBlockPreview(
    apiBase: string,
    payload: BlockPreviewPayload
): string | undefined {
    return recall(hashPayload(apiBase, payload));
}

/**
 * Queue a block for the next batch flush and resolve with its rendered
 * HTML. A cached payload resolves straight away. A failed item rejects
 * with an {@link ApiError} carrying the item's HTTP-equivalent status.
 * Aborting `signal` rejects with an `AbortError` and, if no other block
 * is waiting on the same payload, drops it from the queue; a request
 * already in flight still completes and caches its result.
 *
 * @since 1.17.0
 */
export function queueBlockPreview(
    apiBase: string,
    payload: BlockPreviewPayload,
    signal?: AbortSignal
): Promise<string> {
    const hash = hashPayload(apiBase, payload);
    const cached = recall(hash);

    if (cached !== undefined) {
        return Promise.resolve(cached);
    }

    if (signal?.aborted) {
        return Promise.reject(abortError());
    }

    return new Promise<string>((resolve, reject) => {
        let entry = inFlight.get(hash) ?? queued.get(hash);

        if (entry === undefined) {
            entry = { apiBase, payload, waiters: new Set() };
            queued.set(hash, entry);
        }

        const target = entry;
        const waiter: Waiter = { resolve, reject };
        target.waiters.add(waiter);

        if (queued.get(hash) === target) {
            scheduleFlush();
        }

        signal?.addEventListener(
            'abort',
            () => {
                if (!target.waiters.delete(waiter)) {
                    return;
                }

                if (target.waiters.size === 0 && queued.get(hash) === target) {
                    queued.delete(hash);
                }

                reject(abortError());
            },
            { once: true }
        );
    });
}

/**
 * Drop the session cache and any queued, unsent payloads. Exposed for
 * tests.
 *
 * @since 1.17.0
 */
export function clearBlockPreviewCache(): void {
    if (flushTimer !== null) {
        clearTimeout(flushTimer);
        flushTimer = null;
    }

    cache.clear();
    queued.clear();
    inFlight.clear();
}
