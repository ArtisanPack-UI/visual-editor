/**
 * Client-side site-meta resolution helpers.
 *
 * Mirrors the behaviour of the server-side
 * `ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver`.
 * Hosts pass a `SiteMeta` envelope (typically fetched server-side from
 * cms-framework's `/api/v1/settings/site` and injected into the page
 * payload) and this module pre-walks the block tree to stamp the
 * `_resolvedSite*` attributes that the `core/site-title`,
 * `core/site-tagline`, and `core/site-logo` renderers consume.
 *
 * A bootstrap-time `setDefaultSiteMeta()` lets apps establish a global
 * fallback so callers don't have to thread the prop through every
 * `BlockTree` instance — the per-render `siteMeta` prop wins when both
 * are supplied.
 */

import type { Block } from './types';

export interface SiteMeta {
    title?: string | null;
    description?: string | null;
    url?: string | null;
    logoUrl?: string | null;
}

const SITE_META_BLOCKS = new Set([
    'core/site-title',
    'core/site-tagline',
    'core/site-logo',
]);

let defaultSiteMeta: SiteMeta | null = null;

/**
 * Establishes a process-wide default `siteMeta`. Useful for hosts that
 * fetch site identity once at boot and want every `BlockTree` instance
 * to inherit it without explicit prop wiring.
 */
export function setDefaultSiteMeta(meta: SiteMeta | null): void {
    defaultSiteMeta = meta;
}

/**
 * Returns the currently-configured default. Mostly useful for tests
 * and devtools — production callers should rely on the prop fallback
 * inside {@link inlineSiteMeta}.
 */
export function getDefaultSiteMeta(): SiteMeta | null {
    return defaultSiteMeta;
}

/**
 * Pre-walks a block tree and stamps `_resolvedSite*` attributes onto
 * every `core/site-*` block, returning a new tree. Existing
 * `_resolved*` attributes win — hosts that already stamped meta
 * upstream keep full control.
 *
 * @param tree The block tree to walk.
 * @param meta Per-render site-meta envelope. When `undefined`, falls
 *             back to the global default set via
 *             {@link setDefaultSiteMeta}.
 */
export function inlineSiteMeta(tree: Block[], meta?: SiteMeta | null): Block[] {
    const resolved = meta ?? defaultSiteMeta;

    if (resolved === null || resolved === undefined) {
        return tree;
    }

    return tree.map((block) => stampBlock(block, resolved));
}

function stampBlock(block: Block, meta: SiteMeta): Block {
    const innerBlocks = Array.isArray(block.innerBlocks)
        ? block.innerBlocks.map((child) => stampBlock(child, meta))
        : block.innerBlocks;

    const name = typeof block.name === 'string' ? block.name : '';

    if (!SITE_META_BLOCKS.has(name)) {
        if (innerBlocks === block.innerBlocks) {
            return block;
        }

        return { ...block, innerBlocks };
    }

    const existing =
        block.attributes !== null && typeof block.attributes === 'object' && !Array.isArray(block.attributes)
            ? (block.attributes as Record<string, unknown>)
            : {};

    const stamped: Record<string, unknown> = {
        _resolvedSiteTitle: coerce(meta.title),
        _resolvedSiteTagline: coerce(meta.description),
        _resolvedSiteUrl: coerce(meta.url),
        _resolvedLogoUrl: coerce(meta.logoUrl),
        ...existing,
    };

    return { ...block, attributes: stamped, innerBlocks };
}

function coerce(value: string | null | undefined): string {
    return typeof value === 'string' ? value : '';
}
