/**
 * Site-editor deep-link contract (#625).
 *
 * The SPA's own routing is pathname-based (`/visual-editor/site/templates/12`
 * — see `use-site-editor-routing.ts`), which is fine for links the site
 * editor hands out itself: it knows the entity's database id. Callers
 * *outside* the SPA do not. The post editor's composed view (#623) knows
 * only the template slug it composed against, and the applied-template
 * endpoint never returns an id.
 *
 * So the deep-link surface is keyed on `slug`, not id, and is carried in
 * the query string rather than the path so it stays clearly separate from
 * the canonical route the SPA rewrites to once it has resolved the slug:
 *
 *   /visual-editor/site?entity=template&slug=single
 *
 * `entity_id` is accepted alongside `slug` for future entity types that
 * have no slug of their own. It is not inert: when present and shaped like
 * an id, the resolver skips the slug lookup entirely and lands on it
 * directly (see `use-deep-link.ts`).
 *
 * Everything in this module is pure so the parse can be unit-tested
 * without a router, a DOM, or a network.
 *
 * @since 1.6.0
 */

/** Entity kinds the deep-link surface understands. */
export type DeepLinkEntity = 'template';

export interface DeepLinkRequest {
    entity: DeepLinkEntity;
    /** Entity slug to resolve. Empty slugs are rejected during parsing. */
    slug: string;
    /**
     * Optional pre-resolved id. Reserved for entity kinds that have no
     * slug; when present the resolver can skip the lookup entirely.
     */
    entityId: string | null;
}

/** Route base the site editor is mounted at by the package's own routes. */
export const SITE_EDITOR_ROUTE_BASE = '/visual-editor/site';

const SUPPORTED_ENTITIES: ReadonlySet<string> = new Set<DeepLinkEntity>([
    'template',
]);

/**
 * Parse a `location.search` string into a deep-link request.
 *
 * Returns `null` for every shape the SPA should treat as "no deep link
 * requested" — no query string, an unsupported `entity` value, or a
 * supported entity with no usable `slug`. An unknown entity is a no-op
 * rather than an error on purpose: the query string is a public URL
 * surface, and a future package version adding `entity=navigation` must
 * not make today's build throw when a user pastes tomorrow's link.
 */
export function parseDeepLink(search: string): DeepLinkRequest | null {
    // `URLSearchParams` accepts a leading `?` and an empty string, so no
    // normalisation is needed beyond handing it the raw value.
    const params = new URLSearchParams(search);
    const entity = params.get('entity')?.trim() ?? '';

    if (!SUPPORTED_ENTITIES.has(entity)) {
        return null;
    }

    const slug = params.get('slug')?.trim() ?? '';

    if (slug === '') {
        return null;
    }

    const entityId = params.get('entity_id')?.trim() ?? '';

    return {
        entity: entity as DeepLinkEntity,
        slug,
        entityId: entityId === '' ? null : entityId,
    };
}

/**
 * Build a deep link to a template's editor view.
 *
 * The counterpart to {@link parseDeepLink}: callers outside the SPA use
 * this rather than hand-assembling the query string, so the two sides of
 * the contract move together. `routeBase` defaults to the package's own
 * mount path; hosts that mount the site editor elsewhere pass theirs.
 */
export function buildTemplateDeepLink(
    slug: string,
    routeBase: string = SITE_EDITOR_ROUTE_BASE
): string {
    const query = new URLSearchParams({ entity: 'template', slug });

    return `${normalizeRouteBase(routeBase)}?${query.toString()}`;
}

/**
 * Trim trailing slashes and refuse anything that isn't a same-origin
 * absolute path. Every `routeBase` source today is server-controlled, but
 * the builders feed an `<a href>` — an absolute URL or a `javascript:`
 * scheme reaching one would be an off-site link rather than a route.
 * Protocol-relative `//host` is rejected for the same reason.
 */
export function normalizeRouteBase(routeBase: string): string {
    const normalized = routeBase.replace(/\/+$/, '');

    if (!normalized.startsWith('/') || normalized.startsWith('//')) {
        return SITE_EDITOR_ROUTE_BASE;
    }

    return normalized;
}
