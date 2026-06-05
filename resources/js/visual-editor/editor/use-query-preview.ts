/**
 * `useQueryPreview` — editor-canvas hook for previewing `core/query`
 * results without saving.
 *
 * POSTs the block's query payload to
 * `/visual-editor/api/query/resolve` (the QueryResolveController under
 * G4c-2) and returns the resolved post records plus a status flag the
 * Edit component can branch on. The hook caches by a stable JSON
 * serialization of the payload so two `core/query` blocks that share
 * the same configuration share one in-flight request.
 *
 * The shape returned matches the renderer-react / renderer-vue
 * `ResolvedPost` envelope so a host that wants to share state between
 * the canvas preview and a server-rendered front-end can pipe the same
 * record through both paths.
 */

import { useEffect, useRef, useState } from 'react';

export interface QueryPreviewPost {
    id: number;
    title?: string;
    excerpt?: string;
    content?: string;
    permalink?: string;
    publishedAt?: string;
    /**
     * Server-formatted display date, mirroring the `F j, Y` format the
     * `PostResolver` stamps onto `_resolvedDateFormatted` for the
     * front-end render. Used by the `artisanpack/post-date` editor
     * preview (#483).
     */
    dateFormatted?: string;
    modifiedAt?: string;
    author?: {
        name?: string;
        bio?: string;
        url?: string;
        avatarUrl?: string;
    } | null;
    featuredImage?: {
        url: string;
        alt?: string;
        width?: number;
        height?: number;
    } | null;
    /**
     * Taxonomy terms keyed by taxonomy slug. Powers the
     * `artisanpack/post-terms` editor preview when inside a resolved
     * query loop (#520).
     */
    terms?: Record<string, ReadonlyArray<{
        name?: string;
        slug?: string;
        url?: string;
    }>>;
    /**
     * Adjacent post pair (previous / next). Powers the
     * `artisanpack/post-navigation-link` editor preview when inside a
     * resolved query loop (#520).
     */
    adjacent?: {
        previous?: { title?: string; url?: string } | null;
        next?: { title?: string; url?: string } | null;
    } | null;
    /**
     * Primary term for the post. Powers the
     * `artisanpack/term-description` editor preview when inside a
     * resolved query loop (#520).
     */
    term?: {
        name?: string;
        slug?: string;
        url?: string;
        description?: string;
    } | null;
}

export type QueryPreviewStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface QueryPreviewState {
    status: QueryPreviewStatus;
    posts: QueryPreviewPost[];
    total: number;
    error: string | null;
}

interface QueryPreviewOptions {
    apiBase?: string;
    /**
     * Extra `RequestInit` overrides merged onto the default request.
     * Pass a stable reference (`useMemo`, module-scoped const, etc.) —
     * the hook reads the value through a ref so referential changes
     * after the first call do not retrigger the fetch effect, but
     * deeply changing values *will* be picked up on the next debounce
     * cycle. Inline `{}` literals are accepted but only the first
     * render's value is observed.
     */
    fetchOptions?: RequestInit;
    /** Debounce window in ms — prevents a fetch storm while the inspector is being adjusted. */
    debounceMs?: number;
}

const DEFAULT_API_BASE = '/visual-editor/api';
const DEFAULT_DEBOUNCE_MS = 300;

const INITIAL_STATE: QueryPreviewState = {
    status: 'idle',
    posts: [],
    total: 0,
    error: null,
};

export function useQueryPreview(
    query: Record<string, unknown> | null | undefined,
    options: QueryPreviewOptions = {}
): QueryPreviewState {
    const apiBase = options.apiBase ?? DEFAULT_API_BASE;
    const debounceMs = options.debounceMs ?? DEFAULT_DEBOUNCE_MS;

    const serialized = serialize(query);

    const [state, setState] = useState<QueryPreviewState>(INITIAL_STATE);
    const abortRef = useRef<AbortController | null>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    // Keep `fetchOptions` in a ref instead of an effect dep so callers
    // that pass an inline `{}` do not retrigger the effect on every
    // render. The effect reads the latest value at fire time, so a
    // changed `fetchOptions` is picked up on the next request cycle
    // (typically debounce + serialized-payload change) without an
    // infinite-rerender risk.
    const fetchOptionsRef = useRef(options.fetchOptions);
    fetchOptionsRef.current = options.fetchOptions;

    useEffect(() => {
        if (serialized === '') {
            setState(INITIAL_STATE);
            return;
        }

        if (timeoutRef.current !== null) {
            clearTimeout(timeoutRef.current);
        }

        if (abortRef.current !== null) {
            abortRef.current.abort();
        }

        setState((prev) => ({ ...prev, status: 'loading', error: null }));

        timeoutRef.current = setTimeout(() => {
            const controller = new AbortController();
            abortRef.current = controller;

            const url = `${apiBase.replace(/\/$/, '')}/query/resolve`;

            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            };

            const csrfToken = readCsrfToken();

            if (csrfToken !== null) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            // Merge user-supplied options first, then layer the request's
            // required keys on top so a caller cannot accidentally drop
            // `Content-Type` / `Accept` / `X-CSRF-TOKEN` / the
            // AbortController signal by passing their own `headers` or
            // `signal`. Caller-provided headers that *don't* collide
            // (e.g. tracing headers) are preserved via the explicit
            // header merge.
            //
            // Normalising via `new Headers(...)` handles all three
            // shapes `RequestInit.headers` accepts (plain object,
            // `Headers` instance, `[string, string][]`); a naive spread
            // would silently drop the latter two.
            const userOptions = fetchOptionsRef.current ?? {};
            const userHeaders: Record<string, string> = {};

            if (userOptions.headers !== undefined && userOptions.headers !== null) {
                new Headers(userOptions.headers).forEach((value, key) => {
                    userHeaders[key] = value;
                });
            }

            const init: RequestInit = {
                ...userOptions,
                method: 'POST',
                credentials: userOptions.credentials ?? 'same-origin',
                headers: { ...userHeaders, ...headers },
                body: serialized,
                signal: controller.signal,
            };

            void fetch(url, init)
                .then(async (response) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    if (!response.ok) {
                        const text = await response.text().catch(() => '');
                        throw new Error(text || `HTTP ${response.status}`);
                    }

                    const json = (await response.json()) as {
                        data: unknown[];
                        meta: { total: number };
                    };

                    setState({
                        status: 'ready',
                        posts: normalizePosts(json.data),
                        total: typeof json.meta?.total === 'number' ? json.meta.total : 0,
                        error: null,
                    });
                })
                .catch((error: unknown) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    setState({
                        status: 'error',
                        posts: [],
                        total: 0,
                        error: error instanceof Error ? error.message : 'Failed to resolve query.',
                    });
                });
        }, debounceMs);

        return () => {
            if (timeoutRef.current !== null) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }

            if (abortRef.current !== null) {
                abortRef.current.abort();
                abortRef.current = null;
            }
        };
    }, [serialized, apiBase, debounceMs]);

    return state;
}

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

    return meta?.content?.trim() || null;
}

function serialize(query: Record<string, unknown> | null | undefined): string {
    if (query === null || query === undefined) {
        return '';
    }

    try {
        return JSON.stringify(stripEmptyDefaults(query));
    } catch {
        return '';
    }
}

/**
 * Upstream `core/query` ships block defaults that aren't meaningful
 * (`pages: 0`, `author: ''`, `sticky: ''`, `taxQuery: ''`,
 * `search: ''`, etc.) but fail server-side validation when posted
 * verbatim. Drop empty / null / placeholder values before the request
 * fires so the QueryRuntime only sees fields the user actually
 * configured.
 *
 * Recurses into nested objects + arrays so a partially-configured
 * `taxQuery: { taxonomy: '', terms: [] }` also collapses to absent
 * instead of failing validation as "missing required `terms`".
 *
 * `queryId` always survives because the inliner needs it to match
 * resolved records to their source block on the public-render path.
 */
function stripEmptyDefaults(query: Record<string, unknown>): Record<string, unknown> {
    const out: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(query)) {
        if (key === 'queryId') {
            out[key] = value;
            continue;
        }

        const pruned = pruneValue(value);

        if (pruned === undefined) {
            continue;
        }

        out[key] = pruned;
    }

    return out;
}

/**
 * Returns the pruned form of `value`, or `undefined` if the value should
 * be dropped entirely (empty / null / placeholder).
 */
function pruneValue(value: unknown): unknown {
    if (value === null || value === undefined || value === '') {
        return undefined;
    }

    if (Array.isArray(value)) {
        const items = value
            .map(pruneValue)
            .filter((item): item is unknown => item !== undefined);

        return items.length === 0 ? undefined : items;
    }

    if (typeof value === 'object') {
        const record = value as Record<string, unknown>;
        const inner: Record<string, unknown> = {};

        for (const [key, child] of Object.entries(record)) {
            const pruned = pruneValue(child);

            if (pruned !== undefined) {
                inner[key] = pruned;
            }
        }

        return Object.keys(inner).length === 0 ? undefined : inner;
    }

    return value;
}

function normalizePosts(items: unknown[]): QueryPreviewPost[] {
    if (!Array.isArray(items)) {
        return [];
    }

    return items
        .filter((item): item is Record<string, unknown> => item !== null && typeof item === 'object')
        .map((item) => mapWpEntityToPost(item));
}

function mapWpEntityToPost(entity: Record<string, unknown>): QueryPreviewPost {
    const id = typeof entity.id === 'number' ? entity.id : Number(entity.id ?? 0);
    const titleRendered =
        typeof entity.title === 'object' && entity.title !== null
            ? String((entity.title as Record<string, unknown>).rendered ?? '')
            : '';
    const excerptRendered =
        typeof entity.excerpt === 'object' && entity.excerpt !== null
            ? String((entity.excerpt as Record<string, unknown>).rendered ?? '')
            : '';

    const preview =
        typeof entity._preview === 'object' && entity._preview !== null
            ? (entity._preview as Record<string, unknown>)
            : {};

    return {
        id,
        title: titleRendered,
        excerpt: excerptRendered,
        publishedAt: typeof entity.date === 'string' ? entity.date : undefined,
        modifiedAt: typeof entity.modified === 'string' ? entity.modified : undefined,
        permalink: typeof entity.link === 'string' ? entity.link : undefined,
        dateFormatted:
            typeof preview.dateFormatted === 'string' ? preview.dateFormatted : undefined,
        author: mapPreviewAuthor(preview.author),
        featuredImage: mapPreviewFeaturedImage(preview.featuredImage),
        terms: mapPreviewTerms(preview.terms),
        adjacent: mapPreviewAdjacent(preview.adjacent),
        term: mapPreviewTerm(preview.term),
    };
}

function mapPreviewTerms(value: unknown): QueryPreviewPost['terms'] {
    if (value === null || typeof value !== 'object') {
        return undefined;
    }

    const out: NonNullable<QueryPreviewPost['terms']> = {};

    for (const [taxonomy, raw] of Object.entries(value as Record<string, unknown>)) {
        if (!Array.isArray(raw)) {
            continue;
        }

        const normalized = raw
            .filter(
                (term): term is Record<string, unknown> =>
                    term !== null && typeof term === 'object'
            )
            .map((term) => {
                const record: { name?: string; slug?: string; url?: string } = {};

                if (typeof term.name === 'string') record.name = term.name;
                if (typeof term.slug === 'string') record.slug = term.slug;
                if (typeof term.url === 'string') record.url = term.url;

                return record;
            })
            // Drop blank records (no name/slug/url) so consumers don't
            // iterate over empty placeholders — mirrors the
            // mapAdjacentEntry / mapPreviewTerm pruning below.
            .filter((record) => Object.keys(record).length > 0);

        if (normalized.length > 0) {
            out[taxonomy] = normalized;
        }
    }

    return Object.keys(out).length === 0 ? undefined : out;
}

function mapPreviewAdjacent(value: unknown): QueryPreviewPost['adjacent'] {
    if (value === null || typeof value !== 'object') {
        return null;
    }

    const record = value as Record<string, unknown>;

    return {
        previous: mapAdjacentEntry(record.previous),
        next: mapAdjacentEntry(record.next),
    };
}

function mapAdjacentEntry(value: unknown): { title?: string; url?: string } | null {
    if (value === null || typeof value !== 'object') {
        return null;
    }

    const record = value as Record<string, unknown>;
    const entry: { title?: string; url?: string } = {};

    if (typeof record.title === 'string') entry.title = record.title;
    if (typeof record.url === 'string') entry.url = record.url;

    if (entry.title === undefined && entry.url === undefined) {
        return null;
    }

    return entry;
}

function mapPreviewTerm(value: unknown): QueryPreviewPost['term'] {
    if (value === null || typeof value !== 'object') {
        return null;
    }

    const record = value as Record<string, unknown>;
    const term: { name?: string; slug?: string; url?: string; description?: string } = {};

    if (typeof record.name === 'string') term.name = record.name;
    if (typeof record.slug === 'string') term.slug = record.slug;
    if (typeof record.url === 'string') term.url = record.url;
    if (typeof record.description === 'string') term.description = record.description;

    return Object.keys(term).length === 0 ? null : term;
}

function mapPreviewAuthor(value: unknown): QueryPreviewPost['author'] {
    if (value === null || typeof value !== 'object') {
        return null;
    }

    const record = value as Record<string, unknown>;
    const author: NonNullable<QueryPreviewPost['author']> = {};

    if (typeof record.name === 'string') {
        author.name = record.name;
    }
    if (typeof record.bio === 'string') {
        author.bio = record.bio;
    }
    if (typeof record.url === 'string') {
        author.url = record.url;
    }
    if (typeof record.avatarUrl === 'string') {
        author.avatarUrl = record.avatarUrl;
    }

    return Object.keys(author).length === 0 ? null : author;
}

function mapPreviewFeaturedImage(value: unknown): QueryPreviewPost['featuredImage'] {
    if (value === null || typeof value !== 'object') {
        return null;
    }

    const record = value as Record<string, unknown>;
    const url = typeof record.url === 'string' ? record.url : '';

    if (url === '') {
        return null;
    }

    const image: NonNullable<QueryPreviewPost['featuredImage']> = { url };

    if (typeof record.alt === 'string') {
        image.alt = record.alt;
    }
    if (typeof record.width === 'number') {
        image.width = record.width;
    }
    if (typeof record.height === 'number') {
        image.height = record.height;
    }

    return image;
}
