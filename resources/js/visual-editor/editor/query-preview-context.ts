/**
 * Shared types + helpers for the `artisanpack/queryPreview` block
 * context — the editor-side analogue of the `_resolved*` payload the
 * server-side `QueryInliner` stamps for the front-end renderers.
 *
 * The query block (`core/query` override + first-party `artisanpack/query`)
 * resolves its configured query via `useQueryPreview` and pipes the
 * resolved record set + paginator state down through a
 * `BlockContextProvider`. Descendant blocks — `post-template`,
 * `query-pagination`, `query-pagination-next/previous/numbers`,
 * `query-title`, `query-no-results` — read this context to render
 * against the real resolved data instead of placeholder values, giving
 * the canvas true WYSIWYG fidelity for the loop (#599).
 *
 * The context key is intentionally namespaced with `artisanpack/` so
 * the upstream `core/query` block defaults don't accidentally collide.
 */

import type { QueryPreviewPost } from './use-query-preview';

export const QUERY_PREVIEW_CONTEXT_KEY = 'artisanpack/queryPreview';

/**
 * Hard cap on the number of post iterations rendered in the canvas
 * regardless of the saved `perPage`. Keeps large loops (e.g. perPage
 * of 50) from tanking canvas perf — the saved attribute is untouched
 * so the front end still renders the full requested page. Issue #599
 * settled on 12 as the visual-rhythm break-even point.
 */
export const QUERY_PREVIEW_ITERATION_CAP = 12;

export interface QueryPreviewContextValue {
    /**
     * Resolved post records returned by
     * `/visual-editor/api/query/resolve`. Up to the canvas cap; the
     * saved query may render more on the front end.
     */
    readonly posts: ReadonlyArray<QueryPreviewPost>;
    /** Total matching posts as reported by the resolver, untrimmed. */
    readonly total: number;
    /**
     * Current paginator page used by the resolver — the canvas always
     * previews page 1 because pagination is not interactive in the
     * editor (issue #599 scope). Front-end render uses the URL-driven
     * page number.
     */
    readonly currentPage: number;
    /**
     * Server-resolved query title (from `_resolvedQueryTitle` once
     * resolved) — empty string when no title has been resolved yet.
     * Used by the `artisanpack/query-title` preview.
     */
    readonly queryTitle: string;
    /**
     * Effective per-page value the canvas is previewing. Mirrors the
     * saved `query.perPage` once the resolver settles; descendants
     * use it to compute pagination numbers.
     */
    readonly perPage: number;
    /** Status passthrough — descendants can render loading shells. */
    readonly status: 'idle' | 'loading' | 'ready' | 'error';
}

/**
 * Read the `artisanpack/queryPreview` value out of a block's
 * `props.context` payload. Returns `null` when the context isn't
 * present (block sits outside a resolved query loop, or the resolver
 * has not produced a value yet). Defensively guards against malformed
 * shapes so a stale saved tree cannot crash a descendant edit.
 */
export function readQueryPreviewContext( context: unknown ): QueryPreviewContextValue | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }

    const value = ( context as Record<string, unknown> )[ QUERY_PREVIEW_CONTEXT_KEY ];

    if ( value === null || typeof value !== 'object' ) {
        return null;
    }

    const record = value as Record<string, unknown>;

    const rawPosts = Array.isArray( record.posts ) ? record.posts : [];
    const posts = rawPosts.filter(
        ( post ): post is QueryPreviewPost =>
            post !== null && typeof post === 'object' && typeof ( post as { id?: unknown } ).id === 'number'
    );

    const total = typeof record.total === 'number' && Number.isFinite( record.total )
        ? Math.max( 0, Math.trunc( record.total ) )
        : 0;

    const currentPage = typeof record.currentPage === 'number' && Number.isFinite( record.currentPage )
        ? Math.max( 1, Math.trunc( record.currentPage ) )
        : 1;

    const queryTitle = typeof record.queryTitle === 'string' ? record.queryTitle : '';

    const perPage = typeof record.perPage === 'number' && Number.isFinite( record.perPage )
        ? Math.max( 0, Math.trunc( record.perPage ) )
        : 0;

    const status = record.status === 'idle' || record.status === 'loading' ||
        record.status === 'ready' || record.status === 'error'
        ? record.status
        : 'idle';

    return { posts, total, currentPage, queryTitle, perPage, status };
}

/**
 * Compute the visible iteration count for the canvas. Caller still
 * iterates the post array; this just returns the effective `slice`
 * length so callers don't sprinkle the `Math.min(perPage, cap)` rule
 * in three places.
 *
 * `perPage` of 0 / undefined falls back to `posts.length` so a zero-
 * configured query still previews what the resolver returned.
 */
export function getQueryPreviewIterationCount(
    posts: ReadonlyArray<QueryPreviewPost>,
    perPage: number | undefined
): number {
    const upper = typeof perPage === 'number' && perPage > 0 ? perPage : posts.length;
    return Math.min( upper, QUERY_PREVIEW_ITERATION_CAP, posts.length );
}
