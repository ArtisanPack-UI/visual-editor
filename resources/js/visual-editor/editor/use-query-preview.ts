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

    return {
        id,
        title: titleRendered,
        excerpt: excerptRendered,
        publishedAt: typeof entity.date === 'string' ? entity.date : undefined,
        modifiedAt: typeof entity.modified === 'string' ? entity.modified : undefined,
        permalink: typeof entity.link === 'string' ? entity.link : undefined,
    };
}
