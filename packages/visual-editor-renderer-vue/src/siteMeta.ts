/**
 * Client-side site-meta resolution helpers (Vue port).
 *
 * Mirrors the React renderer's `siteMeta` API and the server-side
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

export function setDefaultSiteMeta(meta: SiteMeta | null): void {
    defaultSiteMeta = meta;
}

export function getDefaultSiteMeta(): SiteMeta | null {
    return defaultSiteMeta;
}

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
