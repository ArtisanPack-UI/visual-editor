/**
 * Batching loader for pattern card previews (#832).
 *
 * Every `PatternPreview` that scrolls into view asks this loader for its
 * pattern's rendered HTML. Requests made within a short window are
 * gathered into one `POST {apiBase}/patterns/preview` call (split at the
 * server's batch cap), so a grid of cards costs one request rather than
 * one per card.
 *
 * Results are memoized per pattern id and content signature for the life
 * of the page, so re-opening the inserter doesn't re-fetch, while an edited
 * pattern (new signature) does. A failed request resolves its cards to
 * `null` (they keep the block-name fallback) and is dropped from the memo
 * so a later mount can retry.
 */

import type { SiteEditorApiConfig } from '../api-client';

import { previewPatterns, type PatternPreviewBatch } from './api-client';

/** Rendered preview for one pattern. */
export interface PatternPreviewResult {
    readonly html: string;
    /** Shared stylesheet markup from the batch the pattern rendered in. */
    readonly styles: string;
}

export type PatternPreviewFetcher = (
    config: SiteEditorApiConfig,
    ids: readonly string[]
) => Promise<PatternPreviewBatch>;

/** How long to wait for more cards before sending a batch, in ms. */
export const PATTERN_PREVIEW_BATCH_DELAY = 50;

/** Mirrors `PatternPreviewRequest::MAX_BATCH` on the server. */
export const PATTERN_PREVIEW_MAX_BATCH = 24;

interface PendingRequest {
    /** Memo keys waiting on this id — one per content signature seen. */
    readonly keys: Set<string>;
    readonly resolvers: Array<(result: PatternPreviewResult | null) => void>;
}

export class PatternPreviewLoader {
    private readonly results = new Map<
        string,
        Promise<PatternPreviewResult | null>
    >();

    private readonly queue = new Map<string, PendingRequest>();

    private timer: ReturnType<typeof setTimeout> | null = null;

    constructor(
        private readonly config: SiteEditorApiConfig,
        private readonly fetcher: PatternPreviewFetcher = previewPatterns
    ) {}

    /**
     * Resolve a pattern's rendered preview, or `null` when it couldn't be
     * rendered (per-pattern error, network failure, 403).
     *
     * @param id        Pattern id the API hands out (DB id or theme slug).
     * @param signature Changes whenever the pattern's content changes.
     */
    load(id: string, signature: string): Promise<PatternPreviewResult | null> {
        const key = `${id}\u0000${signature}`;
        const existing = this.results.get(key);

        if (existing !== undefined) {
            return existing;
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
        this.schedule();

        return promise;
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

        for (let i = 0; i < pending.length; i += PATTERN_PREVIEW_MAX_BATCH) {
            const chunk = pending.slice(i, i + PATTERN_PREVIEW_MAX_BATCH);

            await this.send(chunk);
        }
    }

    private async send(
        chunk: ReadonlyArray<readonly [string, PendingRequest]>
    ): Promise<void> {
        let batch: PatternPreviewBatch | null = null;

        try {
            batch = await this.fetcher(
                this.config,
                chunk.map(([id]) => id)
            );
        } catch {
            batch = null;
        }

        for (const [id, request] of chunk) {
            const entry = batch?.patterns[id];
            const result =
                batch !== null && typeof entry?.html === 'string'
                    ? { html: entry.html, styles: batch.styles }
                    : null;

            // Forget failures so the card can retry on a later mount;
            // successes stay memoized for the page's lifetime.
            if (result === null) {
                for (const key of request.keys) {
                    this.results.delete(key);
                }
            }

            for (const resolve of request.resolvers) {
                resolve(result);
            }
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
