/**
 * Batching loader for pattern card previews (#832).
 *
 * Every `PatternPreview` that scrolls into view asks this loader for its
 * pattern's rendered HTML. Requests made within a short window are
 * gathered into one `POST {apiBase}/patterns/preview` call (split at the
 * server's batch cap), so a grid of cards costs one request rather than
 * one per card.
 *
 * Results are memoized per pattern id and content signature, so re-opening
 * the inserter doesn't re-fetch, while an edited pattern (new signature)
 * does. When a new signature renders, the id's superseded signatures are
 * evicted, and the memo is capped at {@link PATTERN_PREVIEW_CACHE_LIMIT}
 * entries (least recently used first). A failed request resolves its
 * cards to `null` (they keep the block-name fallback) and is dropped from
 * the memo so a later mount can retry — except for failures that can't
 * succeed on retry: a 403 or a `renderer_unavailable` result turns the
 * loader off for the page, and a 429 pauses it for
 * {@link PATTERN_PREVIEW_RATE_LIMIT_COOLDOWN} ms. Chunks are sent
 * {@link PATTERN_PREVIEW_CONCURRENCY} at a time, and each request is
 * aborted after {@link PATTERN_PREVIEW_TIMEOUT} ms.
 */

import type { SiteEditorApiConfig } from '../api-client';

import {
    previewPatterns,
    type PatternPreviewBatch,
    type PatternPreviewRequestOptions,
} from './api-client';

/** Rendered preview for one pattern. */
export interface PatternPreviewResult {
    readonly html: string;
    /** Shared stylesheet markup from the batch the pattern rendered in. */
    readonly styles: string;
}

export type PatternPreviewFetcher = (
    config: SiteEditorApiConfig,
    ids: readonly string[],
    options?: PatternPreviewRequestOptions
) => Promise<PatternPreviewBatch>;

/** How long to wait for more cards before sending a batch, in ms. */
export const PATTERN_PREVIEW_BATCH_DELAY = 50;

/** Mirrors `PatternPreviewRequest::MAX_BATCH` on the server. */
export const PATTERN_PREVIEW_MAX_BATCH = 24;

/**
 * Most memoized previews a loader keeps before evicting the least
 * recently used.
 *
 * @since 1.13.0
 */
export const PATTERN_PREVIEW_CACHE_LIMIT = 200;

/**
 * Batch requests in flight at once when a flush spans several chunks.
 *
 * @since 1.13.0
 */
export const PATTERN_PREVIEW_CONCURRENCY = 2;

/**
 * Per-request timeout, in ms. A request still pending after this is
 * aborted and its cards keep the fallback.
 *
 * @since 1.13.0
 */
export const PATTERN_PREVIEW_TIMEOUT = 15_000;

/**
 * How long the loader sends nothing after a 429, in ms.
 *
 * @since 1.13.0
 */
export const PATTERN_PREVIEW_RATE_LIMIT_COOLDOWN = 30_000;

/** Per-pattern error meaning the server can't render previews at all. */
const RENDERER_UNAVAILABLE = 'renderer_unavailable';

const RESOLVED_NULL: Promise<PatternPreviewResult | null> = Promise.resolve(null);

interface PendingRequest {
    /** Memo keys waiting on this id — one per content signature seen. */
    readonly keys: Set<string>;
    readonly resolvers: Array<(result: PatternPreviewResult | null) => void>;
}

function memoKey(id: string, signature: string): string {
    return `${id}\u0000${signature}`;
}

function errorStatus(error: unknown): number | null {
    if (error !== null && typeof error === 'object' && 'status' in error) {
        const status = (error as { status: unknown }).status;

        return typeof status === 'number' ? status : null;
    }

    return null;
}

export class PatternPreviewLoader {
    private readonly results = new Map<
        string,
        Promise<PatternPreviewResult | null>
    >();

    private readonly queue = new Map<string, PendingRequest>();

    private timer: ReturnType<typeof setTimeout> | null = null;

    /** Set after a 403 or `renderer_unavailable`: nothing more is sent. */
    private disabled = false;

    /** Epoch ms until which nothing is sent, after a 429. */
    private cooldownUntil = 0;

    constructor(
        private readonly config: SiteEditorApiConfig,
        private readonly fetcher: PatternPreviewFetcher = previewPatterns
    ) {}

    /**
     * Resolve a pattern's rendered preview, or `null` when it couldn't be
     * rendered (per-pattern error, network failure, 403, timeout).
     *
     * @param id        Pattern id the API hands out (DB id or theme slug).
     * @param signature Changes whenever the pattern's content changes.
     */
    load(id: string, signature: string): Promise<PatternPreviewResult | null> {
        if (this.disabled) {
            return RESOLVED_NULL;
        }

        const key = memoKey(id, signature);
        const existing = this.results.get(key);

        if (existing !== undefined) {
            // Refresh the entry's position for LRU eviction.
            this.results.delete(key);
            this.results.set(key, existing);

            return existing;
        }

        if (Date.now() < this.cooldownUntil) {
            return RESOLVED_NULL;
        }

        const promise = new Promise<PatternPreviewResult | null>((resolve) => {
            // The server renders an id's current content whatever
            // signature asked, so one queued request serves them all.
            const pending = this.queue.get(id);

            if (pending !== undefined) {
                pending.keys.add(key);
                pending.resolvers.push(resolve);
            } else {
                this.queue.set(id, { keys: new Set([key]), resolvers: [resolve] });
            }
        });

        this.results.set(key, promise);
        this.evictOverflow();
        this.schedule();

        return promise;
    }

    /** Number of memoized entries. For tests and diagnostics. */
    get size(): number {
        return this.results.size;
    }

    /** Whether a preview for this id and signature is memoized. */
    has(id: string, signature: string): boolean {
        return this.results.has(memoKey(id, signature));
    }

    private evictOverflow(): void {
        while (this.results.size > PATTERN_PREVIEW_CACHE_LIMIT) {
            const oldest = this.results.keys().next();

            if (oldest.done === true) {
                return;
            }

            this.results.delete(oldest.value);
        }
    }

    private schedule(): void {
        if (this.timer !== null) {
            return;
        }

        this.timer = setTimeout(() => {
            this.timer = null;
            void this.flush();
        }, PATTERN_PREVIEW_BATCH_DELAY);
    }

    private async flush(): Promise<void> {
        const pending = Array.from(this.queue.entries());

        this.queue.clear();

        const chunks: Array<Array<readonly [string, PendingRequest]>> = [];

        for (let i = 0; i < pending.length; i += PATTERN_PREVIEW_MAX_BATCH) {
            chunks.push(pending.slice(i, i + PATTERN_PREVIEW_MAX_BATCH));
        }

        let next = 0;
        const worker = async (): Promise<void> => {
            while (next < chunks.length) {
                const chunk = chunks[next++];

                if (chunk !== undefined) {
                    await this.send(chunk);
                }
            }
        };

        await Promise.all(
            Array.from(
                { length: Math.min(PATTERN_PREVIEW_CONCURRENCY, chunks.length) },
                worker
            )
        );
    }

    private async send(
        chunk: ReadonlyArray<readonly [string, PendingRequest]>
    ): Promise<void> {
        let batch: PatternPreviewBatch | null = null;

        // A failure earlier in this flush may already have turned the
        // loader off; don't spend a request on the rest.
        if (!this.disabled && Date.now() >= this.cooldownUntil) {
            const controller =
                typeof AbortController === 'undefined' ? null : new AbortController();
            const timeout = setTimeout(
                () => controller?.abort(),
                PATTERN_PREVIEW_TIMEOUT
            );

            try {
                batch = await this.fetcher(
                    this.config,
                    chunk.map(([id]) => id),
                    controller === null ? undefined : { signal: controller.signal }
                );
            } catch (error: unknown) {
                batch = null;
                this.noteFailure(error);
            } finally {
                clearTimeout(timeout);
            }
        }

        if (
            batch !== null &&
            Object.values(batch.patterns).some(
                (entry) => entry?.error === RENDERER_UNAVAILABLE
            )
        ) {
            this.disabled = true;
        }

        for (const [id, request] of chunk) {
            const entry = batch?.patterns[id];
            const result =
                batch !== null && typeof entry?.html === 'string'
                    ? { html: entry.html, styles: batch.styles }
                    : null;

            if (result === null) {
                // Forget failures so the card can retry on a later mount.
                for (const key of request.keys) {
                    this.results.delete(key);
                }
            } else {
                this.evictSuperseded(id, request.keys);
            }

            for (const resolve of request.resolvers) {
                resolve(result);
            }
        }
    }

    /** Drop memoized signatures of `id` other than the ones just rendered. */
    private evictSuperseded(id: string, current: ReadonlySet<string>): void {
        const prefix = memoKey(id, '');

        for (const key of Array.from(this.results.keys())) {
            if (key.startsWith(prefix) && !current.has(key)) {
                this.results.delete(key);
            }
        }
    }

    private noteFailure(error: unknown): void {
        const status = errorStatus(error);

        if (status === 403) {
            this.disabled = true;
        } else if (status === 429) {
            this.cooldownUntil = Date.now() + PATTERN_PREVIEW_RATE_LIMIT_COOLDOWN;
        }
    }
}

const loaders = new Map<string, PatternPreviewLoader>();

/** Shared loader per API base, so every surface batches together. */
export function getPatternPreviewLoader(apiBase: string): PatternPreviewLoader {
    let loader = loaders.get(apiBase);

    if (loader === undefined) {
        loader = new PatternPreviewLoader({ apiBase });
        loaders.set(apiBase, loader);
    }

    return loader;
}

/** Drop every shared loader and its memoized results. For tests. */
export function resetPatternPreviewLoaders(): void {
    loaders.clear();
}

/**
 * Cheap, stable signature of a pattern's renderable content — a 32-bit
 * FNV-1a hash of the raw markup and block tree. Only used to tell an
 * edited pattern apart from its memoized preview.
 */
export function patternContentSignature(
    rawContent: string | undefined,
    blocks: readonly unknown[] | undefined
): string {
    const source = `${rawContent ?? ''}\u0000${JSON.stringify(blocks ?? [])}`;
    let hash = 0x811c9dc5;

    for (let i = 0; i < source.length; i++) {
        hash ^= source.charCodeAt(i);
        hash = Math.imul(hash, 0x01000193);
    }

    return (hash >>> 0).toString(36);
}
