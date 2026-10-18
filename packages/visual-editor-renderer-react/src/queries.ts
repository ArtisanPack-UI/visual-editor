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

import { phpDateFormat } from './support/phpDateFormat';
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
    /** Post type used for the `type-{value}` class on the iteration `<li>`. Defaults to `post`. */
    type?: string;
    /** Publication status used for the `status-{value}` class on the iteration `<li>`. Defaults to `publish`. */
    status?: string;
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

/**
 * Covers stamped with the post's featured image when `useFeaturedImage`
 * is on (#942), as `PostResolver::FEATURED_IMAGE_COVERS` does.
 */
const FEATURED_IMAGE_COVER_BLOCKS = new Set(['core/cover', 'artisanpack/cover']);

const QUERY_BLOCK_NAMES = new Set(['core/query', 'artisanpack/query']);
const POST_TEMPLATE_BLOCK_NAMES = new Set(['core/post-template', 'artisanpack/post-template']);

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

        if (typeof block.name === 'string' && QUERY_BLOCK_NAMES.has(block.name)) {
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

    const queryInner = Array.isArray(block.innerBlocks) ? (block.innerBlocks as Block[]) : [];
    const resolvedAttributes = {
        ...attributes,
        _resolvedTotal: resolved.total ?? resolved.posts.length,
        _resolvedItems: resolved.posts.length,
    };

    if (resolved.posts.length === 0 || queryInner.length === 0) {
        return {
            ...block,
            attributes: resolvedAttributes,
            innerBlocks: [],
        };
    }

    // Find the post-template child block. Its inner blocks are the
    // per-iteration template that gets cloned once per result;
    // everything else (pagination, no-results) stays alongside it.
    let postTemplateIndex = -1;
    let iterationTemplate: Block[] = [];

    for (let i = 0; i < queryInner.length; i += 1) {
        const child = queryInner[i];
        if (child === null || typeof child !== 'object') {
            continue;
        }

        if (typeof child.name === 'string' && POST_TEMPLATE_BLOCK_NAMES.has(child.name)) {
            postTemplateIndex = i;
            iterationTemplate = Array.isArray(child.innerBlocks) ? (child.innerBlocks as Block[]) : [];
            break;
        }
    }

    // No post-template found — fall back to expanding the query's
    // direct inner blocks once per result, so hosts that drop a custom
    // template without the post-template wrapper are not regressed.
    if (postTemplateIndex === -1) {
        const expandedFlat: Block[] = [];

        for (const post of resolved.posts) {
            for (const child of queryInner) {
                expandedFlat.push(stampPost(cloneBlock(child), post));
            }
        }

        return {
            ...block,
            attributes: resolvedAttributes,
            innerBlocks: expandedFlat,
        };
    }

    const expandedIterations: Block[] = [];
    // Track occurrences so a duplicate post.id within a single result set
    // gets a unique clientId (avoids React reconciliation key collisions
    // when BlockTree uses block.clientId as the React key).
    const seenIds = new Map<number, number>();

    for (const post of resolved.posts) {
        const iterationBlocks: Block[] = [];

        for (const tmplChild of iterationTemplate) {
            iterationBlocks.push(stampPost(cloneBlock(tmplChild), post));
        }

        const postType = typeof post.type === 'string' && post.type !== '' ? post.type : 'post';
        const postStatus =
            typeof post.status === 'string' && post.status !== '' ? post.status : 'publish';

        const occurrence = seenIds.get(post.id) ?? 0;
        seenIds.set(post.id, occurrence + 1);
        const clientId = occurrence === 0 ? `pti-${post.id}` : `pti-${post.id}-${occurrence}`;

        expandedIterations.push({
            clientId,
            name: 'core/post-template-item',
            attributes: {
                postId: post.id,
                className: `post-${post.id} post type-${postType} status-${postStatus}`,
            },
            innerBlocks: iterationBlocks,
        });
    }

    const postTemplate = queryInner[postTemplateIndex];
    const expandedTemplate: Block = {
        ...postTemplate,
        innerBlocks: expandedIterations,
    };

    const newQueryInner = queryInner.slice();
    newQueryInner[postTemplateIndex] = expandedTemplate;

    return {
        ...block,
        attributes: resolvedAttributes,
        innerBlocks: newQueryInner,
    };
}

function stampPost(block: Block, post: ResolvedPost): Block {
    const next = cloneBlock(block);

    if (
        typeof next.name === 'string' &&
        FEATURED_IMAGE_COVER_BLOCKS.has(next.name) &&
        (next.attributes as Record<string, unknown>).useFeaturedImage === true
    ) {
        next.attributes = {
            _resolvedImageUrl: post.featuredImage?.url ?? '',
            _resolvedImageAlt: post.featuredImage?.alt ?? '',
            ...(next.attributes as Record<string, unknown>),
        };
    }

    if (typeof next.name === 'string' && POST_CONTEXT_BLOCKS.has(next.name)) {
        const own =
            typeof next.attributes === 'object' && next.attributes !== null
                ? (next.attributes as Record<string, unknown>)
                : {};

        next.attributes = {
            ...resolvedAttributesFor(next.name, post, own),
            ...own,
        };
    }

    if (Array.isArray(next.innerBlocks) && next.innerBlocks.length > 0) {
        next.innerBlocks = next.innerBlocks.map((child) => stampPost(child, post));
    }

    return next;
}

function resolvedAttributesFor(
    name: string,
    post: ResolvedPost,
    attributes: Record<string, unknown>,
): Record<string, unknown> {
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
        case 'core/post-date': {
            const format = typeof attributes.format === 'string' ? attributes.format : '';

            return {
                _resolvedDate: post.publishedAt ?? '',
                _resolvedDateFormatted: formatHumanDate(post.publishedAt, format),
                _resolvedModifiedDate: post.modifiedAt ?? '',
                _resolvedModifiedDateFormatted: formatHumanDate(post.modifiedAt, format),
                _resolvedPermalink: post.permalink ?? '',
            };
        }
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
 * Carbon's `translatedFormat('F j, Y')` produces. A block `format` (a PHP
 * date format string) is applied through `phpDateFormat()` instead (#941).
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
function formatHumanDate(iso: string | null | undefined, format = ''): string {
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

    if (format.trim() !== '') {
        return phpDateFormat(iso, format, locale);
    }

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
