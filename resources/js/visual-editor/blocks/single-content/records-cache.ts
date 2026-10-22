/**
 * Single Content — shared record cache (#1003).
 *
 * Every single-content block on a page used to fetch its own picker list
 * and selected-record title on mount. This module keeps one cache for
 * the whole editor session instead:
 *
 *  - Canvas previews (the record the block features) are collected for
 *    one microtask and resolved through a single
 *    `POST /visual-editor/api/query/resolve` per content type — the same
 *    resolver the front end renders single-content through — so a page
 *    with many single-content blocks costs one request per content type.
 *  - Picker list requests are keyed by their full URL (collection +
 *    search), and callers share the in-flight promise.
 *  - Title lookups for ids that aren't in a list are batched into one
 *    `?include=` request per collection. Records returned by a list
 *    prime the title cache, so a listed record never needs a lookup.
 *
 * `403` and `404` responses resolve to an empty result and stay cached,
 * so an endpoint the host doesn't serve (or the user can't read) isn't
 * re-requested on every render. Any other non-OK status (a `503` from a
 * missing query runtime, a `5xx`, an expired session) and network
 * failures reject, which evicts the entry so a later call can retry.
 *
 * @since 1.16.0
 */

import {
    mapWpEntityToPost,
    readCsrfToken,
    type QueryPreviewPost,
} from '../../editor/use-query-preview';

export interface PostSummary {
    readonly id: number;
    readonly label: string;
}

export const API_BASE = '/visual-editor/api';

/**
 * Matches the server-side caps (`include` ids, query `perPage`) so a
 * batch never exceeds them.
 */
const MAX_BATCH = 100;

/**
 * Whether a non-OK response is a deterministic "nothing here" answer that
 * is safe to cache for the session.
 */
function isCacheableMiss(response: Response): boolean {
    return response.status === 403 || response.status === 404;
}

export function clampPostId(value: number | string | undefined | null): number {
    const parsed = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }
    return Math.trunc(parsed);
}

export function extractLabel(record: Record<string, unknown>, id: number): string {
    const title = record.title;
    if (typeof title === 'string' && title !== '') {
        return title;
    }
    if (title !== null && typeof title === 'object') {
        const envelope = title as { rendered?: unknown; raw?: unknown };
        if (typeof envelope.rendered === 'string' && envelope.rendered !== '') {
            return envelope.rendered;
        }
        if (typeof envelope.raw === 'string' && envelope.raw !== '') {
            return envelope.raw;
        }
    }
    if (typeof record.slug === 'string' && record.slug !== '') {
        return record.slug;
    }
    return `#${id}`;
}

function readRecords(body: unknown): ReadonlyArray<Record<string, unknown>> {
    const raw = Array.isArray(body)
        ? body
        : body !== null &&
            typeof body === 'object' &&
            Array.isArray((body as { data?: unknown }).data)
          ? (body as { data: unknown[] }).data
          : [];

    return raw.filter(
        (entry): entry is Record<string, unknown> =>
            entry !== null && typeof entry === 'object'
    );
}

interface PendingLoad<T> {
    readonly resolve: (value: T | null) => void;
    readonly reject: (error: unknown) => void;
}

interface Batcher<T> {
    readonly load: (group: string, id: number) => Promise<T | null>;
    readonly prime: (group: string, id: number, value: T) => void;
    readonly reset: () => void;
}

/**
 * Builds a cache that collects the ids requested for each group during
 * one microtask and resolves them through `fetchGroup` in chunks of
 * {@see MAX_BATCH}. Ids the response doesn't contain resolve to `null`.
 */
function createBatcher<T>(
    fetchGroup: (group: string, ids: ReadonlyArray<number>) => Promise<Map<number, T>>
): Batcher<T> {
    const cache: Map<string, Promise<T | null>> = new Map();
    const pending: Map<string, Map<number, PendingLoad<T>>> = new Map();
    let flushScheduled = false;

    async function flushGroup(group: string, batch: Map<number, PendingLoad<T>>): Promise<void> {
        const ids = [...batch.keys()];

        for (let offset = 0; offset < ids.length; offset += MAX_BATCH) {
            const chunk = ids.slice(offset, offset + MAX_BATCH);

            try {
                const found = await fetchGroup(group, chunk);
                for (const id of chunk) {
                    batch.get(id)?.resolve(found.get(id) ?? null);
                }
            } catch (error) {
                for (const id of chunk) {
                    batch.get(id)?.reject(error);
                }
            }
        }
    }

    function flush(): void {
        flushScheduled = false;
        const groups = [...pending.entries()];
        pending.clear();

        for (const [group, batch] of groups) {
            void flushGroup(group, batch);
        }
    }

    return {
        load(group, id) {
            const key = `${group}:${id}`;
            const cached = cache.get(key);
            if (cached !== undefined) {
                return cached;
            }

            const promise = new Promise<T | null>((resolve, reject) => {
                let batch = pending.get(group);
                if (batch === undefined) {
                    batch = new Map();
                    pending.set(group, batch);
                }
                batch.set(id, { resolve, reject });

                if (!flushScheduled) {
                    flushScheduled = true;
                    queueMicrotask(flush);
                }
            });
            cache.set(key, promise);

            promise.catch(() => {
                if (cache.get(key) === promise) {
                    cache.delete(key);
                }
            });

            return promise;
        },
        prime(group, id, value) {
            const key = `${group}:${id}`;
            if (!cache.has(key)) {
                cache.set(key, Promise.resolve(value));
            }
        },
        reset() {
            cache.clear();
            pending.clear();
            flushScheduled = false;
        },
    };
}

async function fetchSummaries(url: string): Promise<ReadonlyArray<PostSummary>> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        if (isCacheableMiss(response)) {
            return [];
        }
        throw new Error(`HTTP ${response.status}`);
    }

    const summaries: PostSummary[] = [];
    for (const record of readRecords(await response.json())) {
        const id = clampPostId(record.id as number | string);
        if (id !== 0) {
            summaries.push({ id, label: extractLabel(record, id) });
        }
    }
    return summaries;
}

const labels = createBatcher<string>(async (collection, ids) => {
    const params = new URLSearchParams({ include: ids.join(',') });
    const records = await fetchSummaries(`${API_BASE}/${collection}?${params.toString()}`);

    return new Map(records.map((record) => [record.id, record.label]));
});

const previews = createBatcher<QueryPreviewPost>(async (postType, ids) => {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };
    const csrfToken = readCsrfToken();
    if (csrfToken !== null) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const response = await fetch(`${API_BASE}/query/resolve`, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({ postType, postIn: ids, perPage: ids.length }),
    });

    if (!response.ok) {
        if (isCacheableMiss(response)) {
            return new Map();
        }
        throw new Error(`HTTP ${response.status}`);
    }

    const posts = readRecords(await response.json()).map(mapWpEntityToPost);

    return new Map(posts.filter((post) => post.id > 0).map((post) => [post.id, post]));
});

const listCache: Map<string, Promise<ReadonlyArray<PostSummary>>> = new Map();

/**
 * Fetch the picker list for a collection, optionally filtered by a
 * search term. Identical requests share one promise.
 */
export function fetchRecordList(
    collection: string,
    search: string
): Promise<ReadonlyArray<PostSummary>> {
    const params = new URLSearchParams({ per_page: '50' });
    const term = search.trim();
    if (term !== '') {
        params.set('search', term);
    }
    const url = `${API_BASE}/${collection}?${params.toString()}`;

    const cached = listCache.get(url);
    if (cached !== undefined) {
        return cached;
    }

    const promise = fetchSummaries(url).then((records) => {
        for (const record of records) {
            labels.prime(collection, record.id, record.label);
        }
        return records;
    });
    listCache.set(url, promise);

    promise.catch(() => {
        if (listCache.get(url) === promise) {
            listCache.delete(url);
        }
    });

    return promise;
}

/**
 * Resolve the display title for one record. Lookups requested in the
 * same tick are batched into one `?include=` request per collection.
 * Resolves to `null` when the record can't be found.
 */
export function fetchRecordLabel(collection: string, id: number): Promise<string | null> {
    return labels.load(collection, id);
}

/**
 * Resolve the canvas preview for one record of a content type (singular
 * slug, e.g. `package`). Previews requested in the same tick are
 * batched into one `query/resolve` request per content type. Resolves
 * to `null` when the resolver doesn't return the record.
 */
export function fetchRecordPreview(postType: string, id: number): Promise<QueryPreviewPost | null> {
    return previews.load(postType, id);
}

/**
 * Test-only cache reset. Not part of the public export surface.
 * @internal
 */
export function __resetRecordsCache(): void {
    listCache.clear();
    labels.reset();
    previews.reset();
}
