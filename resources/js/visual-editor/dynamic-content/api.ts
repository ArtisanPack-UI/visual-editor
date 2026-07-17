/**
 * Dynamic Content editor-side API client.
 *
 * Thin wrapper over the `/visual-editor/api/dynamic-content/*` endpoints
 * with a per-session cache for the sources listing (rarely changes) and
 * a small debounced batching layer for token resolution (populated by
 * dozens of chips at once).
 *
 * @since 1.4.0
 */

export const DC_API_BASE = '/visual-editor/api/dynamic-content';

export interface DynamicContentField {
    slug: string;
    label: string;
    type: string;
}

export interface DynamicContentSource {
    slug: string;
    label: string;
    cardinality: 'singleton' | 'collection';
    origin: 'db' | 'code';
    description?: string;
    icon?: string;
    fields: DynamicContentField[];
}

let sourcesPromise: Promise<DynamicContentSource[]> | null = null;

export function fetchSources(force = false): Promise<DynamicContentSource[]> {
    if (!force && sourcesPromise) return sourcesPromise;

    sourcesPromise = fetch(`${DC_API_BASE}/sources`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))))
        .then((body) => (Array.isArray(body?.sources) ? (body.sources as DynamicContentSource[]) : []))
        .catch((e) => {
            sourcesPromise = null;
            throw e;
        });

    return sourcesPromise;
}

interface PendingBatch {
    tokens: Set<string>;
    resolvers: Array<(values: Record<string, unknown>) => void>;
    rejecters: Array<(reason: unknown) => void>;
    timer: ReturnType<typeof setTimeout> | null;
}

const cache: Map<string, unknown> = new Map();
let pending: PendingBatch | null = null;
const BATCH_DELAY_MS = 40;

export function resolveTokens(tokens: string[]): Promise<Record<string, unknown>> {
    const unique = Array.from(new Set(tokens)).filter((t) => typeof t === 'string' && t !== '');

    if (unique.length === 0) return Promise.resolve({});

    const cachedValues: Record<string, unknown> = {};
    const missing: string[] = [];

    for (const token of unique) {
        if (cache.has(token)) {
            cachedValues[token] = cache.get(token);
        } else {
            missing.push(token);
        }
    }

    if (missing.length === 0) return Promise.resolve(cachedValues);

    return new Promise<Record<string, unknown>>((resolve, reject) => {
        if (!pending) {
            pending = { tokens: new Set(), resolvers: [], rejecters: [], timer: null };
        }
        const p = pending;
        for (const token of missing) p.tokens.add(token);
        p.resolvers.push((values) => {
            const combined: Record<string, unknown> = { ...cachedValues };
            for (const token of unique) {
                combined[token] = token in values ? values[token] : cachedValues[token] ?? null;
            }
            resolve(combined);
        });
        p.rejecters.push(reject);
        if (!p.timer) {
            p.timer = setTimeout(flushBatch, BATCH_DELAY_MS);
        }
    });
}

function flushBatch(): void {
    if (!pending) return;
    const batch = pending;
    pending = null;

    const tokens = Array.from(batch.tokens);

    fetch(`${DC_API_BASE}/resolve`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ tokens }),
    })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))))
        .then((body) => {
            const values = (body?.values ?? {}) as Record<string, unknown>;
            // Only cache resolved (non-null) values. Caching misses as
            // null poisons the client's view of a token that was
            // simply not yet registered when it was first requested —
            // authoring the token afterwards would show `[Missing:…]`
            // forever until a hard refresh, because the null hit
            // short-circuits every subsequent resolveTokens() call.
            for (const token of tokens) {
                if (token in values && values[token] !== null && values[token] !== undefined) {
                    cache.set(token, values[token]);
                }
            }
            for (const resolve of batch.resolvers) resolve(values);
        })
        .catch((err) => {
            for (const rej of batch.rejecters) rej(err);
        });
}

/**
 * Invalidate the resolver cache. Call after admin changes propagate.
 *
 * @since 1.4.0
 */
export function invalidateTokenCache(): void {
    cache.clear();
}

/**
 * Flatten a list of sources into `source.field` token entries with
 * per-entry metadata used by the autocomplete matcher and the inserter
 * modal preview.
 *
 * @since 1.4.0
 */
export function flattenTokens(sources: DynamicContentSource[]): Array<{
    token: string;
    sourceSlug: string;
    sourceLabel: string;
    fieldSlug: string;
    fieldLabel: string;
    fieldType: string;
    cardinality: 'singleton' | 'collection';
}> {
    const rows: Array<{
        token: string;
        sourceSlug: string;
        sourceLabel: string;
        fieldSlug: string;
        fieldLabel: string;
        fieldType: string;
        cardinality: 'singleton' | 'collection';
    }> = [];

    for (const source of sources) {
        for (const field of source.fields) {
            rows.push({
                token: `${source.slug}.${field.slug}`,
                sourceSlug: source.slug,
                sourceLabel: source.label,
                fieldSlug: field.slug,
                fieldLabel: field.label,
                fieldType: field.type,
                cardinality: source.cardinality,
            });
        }
    }

    return rows;
}
