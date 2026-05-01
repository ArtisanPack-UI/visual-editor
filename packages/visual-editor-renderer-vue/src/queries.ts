/**
 * Client-side `core/query` expansion.
 *
 * Mirrors the server-side
 * `ArtisanPackUI\VisualEditor\Resources\QueryInliner` so a
 * pre-resolved set of post records can drive the same per-result
 * expansion the Blade renderer produces. Hosts that pre-fetch query
 * results server-side (typical for Inertia + React) hand them to
 * `<BlockTree queryResults={...} />` and the inliner walks the saved
 * tree, replacing every `core/query` block with one stamped copy of
 * its inner blocks per result.
 *
 * The inliner does not call the network — clients that need to fetch
 * results on the fly use the `useQueryPreview` hook instead.
 */

import type { Block } from './types';

export const QUERY_RESOLUTION_ERROR_NO_RESULTS = 'no-results';

export interface ResolvedQuery {
    /** Identifier matching a `core/query` block's `query.queryId` attribute. */
    queryId: string | number;
    /** Posts returned by the resolver in result order. */
    posts: ResolvedPost[];
    /** Total before perPage / offset are applied; used by the count placeholder. */
    total?: number;
}

export interface ResolvedPost {
    id: number;
    title?: string;
    excerpt?: string;
    content?: string;
    permalink?: string;
    publishedAt?: string;
    modifiedAt?: string;
    author?: ResolvedAuthor | null;
    featuredImage?: ResolvedFeaturedImage | null;
}

export interface ResolvedAuthor {
    name?: string;
    bio?: string;
    url?: string;
    avatarUrl?: string;
}

export interface ResolvedFeaturedImage {
    url: string;
    alt?: string;
    width?: number;
    height?: number;
}

interface InlineQueriesOptions {
    queries: ResolvedQuery[];
}

const POST_CONTEXT_BLOCKS = new Set([
    'core/post-title',
    'core/post-content',
    'core/post-excerpt',
    'core/post-date',
    'core/post-author',
    'core/post-featured-image',
]);

export function inlineQueries(tree: Block[], options: InlineQueriesOptions): Block[] {
    const queriesById = new Map<string, ResolvedQuery>();

    for (const query of options.queries) {
        queriesById.set(String(query.queryId), query);
    }

    return walk(tree, queriesById);
}

function walk(tree: Block[], queries: Map<string, ResolvedQuery>): Block[] {
    const out: Block[] = [];

    for (const block of tree) {
        if (block === null || typeof block !== 'object') {
            continue;
        }

        if (block.name === 'core/query') {
            out.push(expandQuery(block, queries));
            continue;
        }

        if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
            out.push({
                ...block,
                innerBlocks: walk(block.innerBlocks, queries),
            });
            continue;
        }

        out.push(block);
    }

    return out;
}

function expandQuery(block: Block, queries: Map<string, ResolvedQuery>): Block {
    const attributes =
        block.attributes !== null && typeof block.attributes === 'object'
            ? (block.attributes as Record<string, unknown>)
            : {};

    const queryAttrs =
        attributes.query !== null && typeof attributes.query === 'object'
            ? (attributes.query as Record<string, unknown>)
            : attributes;

    const queryId = queryAttrs.queryId;
    const resolved =
        typeof queryId === 'string' || typeof queryId === 'number'
            ? queries.get(String(queryId))
            : undefined;

    if (resolved === undefined) {
        return {
            ...block,
            attributes: { ...attributes, _resolutionError: QUERY_RESOLUTION_ERROR_NO_RESULTS },
            innerBlocks: [],
        };
    }

    const template = Array.isArray(block.innerBlocks) ? (block.innerBlocks as Block[]) : [];
    const expanded: Block[] = [];

    for (const post of resolved.posts) {
        for (const child of template) {
            expanded.push(stampPost(cloneBlock(child), post));
        }
    }

    return {
        ...block,
        attributes: {
            ...attributes,
            _resolvedTotal: resolved.total ?? resolved.posts.length,
            _resolvedItems: resolved.posts.length,
        },
        innerBlocks: expanded,
    };
}

function stampPost(block: Block, post: ResolvedPost): Block {
    const next = cloneBlock(block);

    if (typeof next.name === 'string' && POST_CONTEXT_BLOCKS.has(next.name)) {
        next.attributes = {
            ...resolvedAttributesFor(next.name, post),
            ...(typeof next.attributes === 'object' && next.attributes !== null
                ? (next.attributes as Record<string, unknown>)
                : {}),
        };
    }

    if (Array.isArray(next.innerBlocks) && next.innerBlocks.length > 0) {
        next.innerBlocks = next.innerBlocks.map((child) => stampPost(child, post));
    }

    return next;
}

function resolvedAttributesFor(name: string, post: ResolvedPost): Record<string, unknown> {
    switch (name) {
        case 'core/post-title':
            return {
                _resolvedTitle: post.title ?? '',
                _resolvedPermalink: post.permalink ?? '',
            };
        case 'core/post-content':
            return {
                _resolvedContent: post.content ?? '',
            };
        case 'core/post-excerpt':
            return {
                _resolvedExcerpt: post.excerpt ?? '',
                _resolvedPermalink: post.permalink ?? '',
            };
        case 'core/post-date':
            return {
                _resolvedDate: post.publishedAt ?? '',
                _resolvedDateFormatted: formatHumanDate(post.publishedAt),
                _resolvedModifiedDate: post.modifiedAt ?? '',
                _resolvedModifiedDateFormatted: formatHumanDate(post.modifiedAt),
                _resolvedPermalink: post.permalink ?? '',
            };
        case 'core/post-author':
            return {
                _resolvedAuthorName: post.author?.name ?? '',
                _resolvedAuthorBio: post.author?.bio ?? '',
                _resolvedAuthorUrl: post.author?.url ?? '',
                _resolvedAuthorAvatar: post.author?.avatarUrl ?? '',
            };
        case 'core/post-featured-image':
            return {
                _resolvedImageUrl: post.featuredImage?.url ?? '',
                _resolvedImageAlt: post.featuredImage?.alt ?? '',
                _resolvedImageWidth: post.featuredImage?.width ?? 0,
                _resolvedImageHeight: post.featuredImage?.height ?? 0,
                _resolvedPermalink: post.permalink ?? '',
            };
        default:
            return {};
    }
}

/**
 * Format an ISO timestamp as a human-readable "F j, Y" date so the
 * client-side `_resolvedDateFormatted` matches the server-side output
 * Carbon's `translatedFormat('F j, Y')` produces.
 *
 * Locale resolution prefers the document's `<html lang>` so a host that
 * sets `lang="es"` gets Spanish month names (matching the server-side
 * locale via Carbon's translation). Timezone is pinned to `UTC` so the
 * day boundary matches what the server emits — without this, a post
 * stored at `2026-04-20T23:30:00Z` would render as April 20 server-side
 * but April 21 in a viewer's `Asia/Tokyo` browser. Falls back to the
 * raw string when the date is unparseable so the renderer never throws
 * on malformed input.
 */
function formatHumanDate(iso: string | null | undefined): string {
    if (iso === null || iso === undefined || iso === '') {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    const locale =
        typeof document !== 'undefined' && document.documentElement?.lang
            ? document.documentElement.lang
            : 'en-US';

    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

function cloneBlock(block: Block): Block {
    return {
        ...block,
        attributes:
            typeof block.attributes === 'object' && block.attributes !== null
                ? { ...(block.attributes as Record<string, unknown>) }
                : {},
        innerBlocks: Array.isArray(block.innerBlocks)
            ? block.innerBlocks.map((child) => cloneBlock(child))
            : [],
    };
}
