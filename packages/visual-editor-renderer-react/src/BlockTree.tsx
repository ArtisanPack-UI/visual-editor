/**
 * Public React component for rendering a saved block tree.
 *
 * Walks a `{ clientId, name, attributes, innerBlocks }` block tree the visual
 * editor persists and renders it to React elements. Each block name is looked
 * up in the shared block registry; anything unregistered falls through to the
 * {@link DynamicBlock} component, which fetches the server-rendered HTML from
 * the visual-editor preview endpoint.
 *
 * `tree` accepts the array form the editor persists, a JSON-encoded string of
 * that shape, or `null`/`undefined` for an empty render.
 *
 * Optionally accepts a `templateParts` map. When provided, every
 * `core/template-part` block in the tree is replaced (pre-walk) with
 * its referenced part's blocks via {@link inlineTemplateParts}. The
 * registered `core/template-part` renderer wraps the resolved blocks in
 * a semantic element so the surrounding layout still gets the part's
 * regions.
 */

import { Fragment, useMemo } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { DynamicBlock } from './DynamicBlock';
import { GlobalStyles } from './GlobalStyles';
import { UnknownBlock } from './blocks/unknownBlock';
import {
    DEFAULT_MAX_PATTERN_DEPTH,
    inlinePatterns,
} from './patterns';
import type { PatternRecord } from './patterns';
import { inlineQueries } from './queries';
import type { ResolvedQuery } from './queries';
import { getBlockRenderer } from './registry';
import { inlineSiteMeta } from './siteMeta';
import type { SiteMeta } from './siteMeta';
import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    inlineTemplateParts,
} from './templateParts';
import type { TemplatePartRecord } from './templateParts';
import type { Block } from './types';

const DEFAULT_ENDPOINT = '/visual-editor/api/blocks/preview';

export interface BlockTreeProps {
    tree: Block[] | string | null | undefined;
    dynamicBlockEndpoint?: string;
    fetchOptions?: RequestInit;
    templateParts?: TemplatePartRecord[];
    /**
     * Synced-pattern records keyed by id. When supplied, every
     * `core/block` reference in the tree is replaced (pre-walk) with its
     * referenced pattern's blocks via {@link inlinePatterns}.
     */
    patterns?: PatternRecord[];
    defaultTheme?: string;
    maxTemplatePartDepth?: number;
    maxPatternDepth?: number;
    /**
     * Compiled global-styles CSS — same string the PHP
     * `GlobalStylesCssProvider` emits on Blade pages. When supplied,
     * `BlockTree` injects it as a `<style>` tag inside the rendered
     * fragment so the React-rendered page matches the canvas.
     */
    globalStylesCss?: string | null;
    /**
     * Site-meta envelope used to resolve `core/site-title`,
     * `core/site-tagline`, and `core/site-logo` blocks. Typically
     * fetched server-side from cms-framework's
     * `/api/v1/settings/site` endpoint and injected into the page
     * payload. When omitted, falls back to the global default
     * registered via `setDefaultSiteMeta`. Existing `_resolvedSite*`
     * attributes on a block always win, so hosts can override per
     * block when needed.
     */
    siteMeta?: SiteMeta | null;
    /**
     * Pre-resolved `core/query` results keyed by `queryId`. When
     * supplied, every `core/query` block in the tree is replaced with
     * one stamped copy of its inner blocks per result via
     * {@link inlineQueries}. Hosts that pre-fetch results server-side
     * (Inertia + React) pass them here; for canvas / on-the-fly
     * fetching see `useQueryPreview`.
     */
    queryResults?: ResolvedQuery[];
}

export function BlockTree({
    tree,
    dynamicBlockEndpoint = DEFAULT_ENDPOINT,
    fetchOptions,
    templateParts,
    patterns,
    defaultTheme,
    maxTemplatePartDepth = DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    maxPatternDepth = DEFAULT_MAX_PATTERN_DEPTH,
    globalStylesCss,
    siteMeta,
    queryResults,
}: BlockTreeProps): ReactElement {
    const blocks = useMemo(() => {
        const normalized = normalizeTree(tree);

        // Run the inliner whenever templateParts is supplied at all, even
        // when it's an empty array — the host explicitly told us "no parts
        // available," so any core/template-part references in the tree
        // should be flagged unresolved instead of silently rendering an
        // empty wrapper.
        const withParts =
            templateParts === undefined
                ? normalized
                : inlineTemplateParts(normalized, {
                      parts: templateParts,
                      defaultTheme,
                      maxDepth: maxTemplatePartDepth,
                  });

        // Same opt-in semantics as templateParts: an empty `patterns`
        // array means "no synced patterns available," so any `core/block`
        // references should resolve to an unresolved marker instead of
        // falling through to the dynamic-block fallback. Pattern inlining
        // runs after template-part inlining so a part that itself
        // contains a synced-pattern reference resolves in the same pass.
        const withPatterns =
            patterns === undefined
                ? withParts
                : inlinePatterns(withParts, {
                      patterns,
                      maxDepth: maxPatternDepth,
                  });

        const withSiteMeta = inlineSiteMeta(withPatterns, siteMeta);

        // Query inlining runs last so a `core/query` block reachable only
        // through a resolved template part / pattern still gets its loop
        // expanded.
        return queryResults === undefined
            ? withSiteMeta
            : inlineQueries(withSiteMeta, { queries: queryResults });
    }, [
        tree,
        templateParts,
        patterns,
        defaultTheme,
        maxTemplatePartDepth,
        maxPatternDepth,
        siteMeta,
        queryResults,
    ]);

    return (
        <>
            <GlobalStyles css={globalStylesCss} />
            {blocks.map((block, index) =>
                renderBlock(block, index, dynamicBlockEndpoint, fetchOptions)
            )}
        </>
    );
}

function renderBlock(
    block: Block,
    index: number,
    endpoint: string,
    fetchOptions: RequestInit | undefined
): ReactNode {
    const name = typeof block.name === 'string' ? block.name.trim() : '';

    if (name === '') {
        return null;
    }

    const attributes =
        block.attributes !== null && typeof block.attributes === 'object' && !Array.isArray(block.attributes)
            ? (block.attributes as Record<string, unknown>)
            : {};

    const innerBlocks = Array.isArray(block.innerBlocks) ? block.innerBlocks.filter(isBlock) : [];
    const key = typeof block.clientId === 'string' && block.clientId !== '' ? block.clientId : `${name}-${index}`;

    const renderedChildren =
        innerBlocks.length === 0
            ? null
            : innerBlocks.map((child, childIndex) =>
                  renderBlock(child, childIndex, endpoint, fetchOptions)
              );

    const Renderer = getBlockRenderer(name);

    if (Renderer !== undefined) {
        return (
            <Renderer
                key={key}
                name={name}
                attributes={attributes}
                innerBlocks={innerBlocks}
            >
                {renderedChildren === null ? null : <Fragment>{renderedChildren}</Fragment>}
            </Renderer>
        );
    }

    return (
        <DynamicBlock
            key={key}
            name={name}
            attributes={attributes}
            endpoint={endpoint}
            fetchOptions={fetchOptions}
        />
    );
}

function normalizeTree(tree: Block[] | string | null | undefined): Block[] {
    if (tree === null || tree === undefined) {
        return [];
    }

    if (typeof tree === 'string') {
        try {
            const parsed = JSON.parse(tree);

            return Array.isArray(parsed) ? parsed.filter(isBlock) : [];
        } catch {
            return [];
        }
    }

    return Array.isArray(tree) ? tree.filter(isBlock) : [];
}

function isBlock(value: unknown): value is Block {
    return (
        value !== null &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        typeof (value as { name?: unknown }).name === 'string'
    );
}

export { UnknownBlock };
