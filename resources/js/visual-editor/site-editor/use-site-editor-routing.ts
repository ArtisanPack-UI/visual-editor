/**
 * Site-editor routing hook.
 *
 * Owns the URL ↔ active-section mapping for the site-editor SPA. The
 * Laravel route (`/visual-editor/site/{path?}`) is a single catch-all
 * that hands control to the React shell; this hook reads the trailing
 * path on mount, exposes the parsed `{section, entityId}`, and pushes
 * to `window.history` when the user picks a different section in the
 * navigator. `popstate` is wired so browser back/forward stay in sync.
 *
 * Kept separate from the React component tree so unit tests can drive
 * navigation without rendering a full DOM.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';

import { normalizeRouteBase } from './deep-link';
import {
    DEFAULT_SECTION_ID,
    findSectionBySlug,
    type SiteEditorSectionId,
} from './sections';

export interface SiteEditorLocation {
    section: SiteEditorSectionId;
    /** Entity id when a sub-path is present; `null` for list mode. */
    entityId: string | null;
}

export interface SiteEditorNavigateOptions {
    /**
     * Replace the current history entry instead of pushing a new one. Use
     * for landings the user did not ask for — a deep-link resolution is a
     * *rewrite* of the entry they arrived on, and pushing would leave Back
     * pointing at a query-string URL the popstate handler cannot re-read.
     */
    replace?: boolean;
}

export interface SiteEditorRouting extends SiteEditorLocation {
    /**
     * Navigate to a different section (and optionally a specific entity)
     * without a full page reload. Pushes a new history entry; falls back
     * to direct state update when running outside a browser (SSR).
     */
    navigate: (
        section: SiteEditorSectionId,
        entityId?: string | null,
        options?: SiteEditorNavigateOptions
    ) => void;
}

interface UseSiteEditorRoutingOptions {
    /** Path prefix the SPA owns (e.g. `/visual-editor/site`). */
    routeBase: string;
}

/**
 * Pure URL parser. Exported for tests so they can assert the mapping
 * without running the hook.
 */
export function parseSiteEditorPath(
    pathname: string,
    routeBase: string
): SiteEditorLocation {
    // The same normalization `buildSiteEditorPath` applies. Without it the
    // two disagree for a non-canonical base: navigation would write URLs
    // under the canonical path while the parser, still matching the raw
    // base, read every one of them as the default section.
    const normalizedBase = normalizeRouteBase(routeBase);

    if (
        pathname !== normalizedBase &&
        !pathname.startsWith(`${normalizedBase}/`)
    ) {
        return { section: DEFAULT_SECTION_ID, entityId: null };
    }

    const trailing = pathname
        .slice(normalizedBase.length)
        .replace(/^\/+/, '')
        .replace(/\/+$/, '');

    if (trailing === '') {
        return { section: DEFAULT_SECTION_ID, entityId: null };
    }

    const [sectionSlug, ...rest] = trailing.split('/');
    const section = findSectionBySlug(sectionSlug);

    if (section === null) {
        return { section: DEFAULT_SECTION_ID, entityId: null };
    }

    const entityId = rest.length > 0 ? decodePathSegment(rest.join('/')) : null;

    return { section: section.id, entityId };
}

/**
 * `decodeURIComponent` throws on malformed escape sequences (e.g. a
 * lone `%`). The router should never crash on a hand-typed URL — fall
 * back to the raw string when the segment isn't decodable so the user
 * still lands on the resolved section.
 */
function decodePathSegment(value: string): string {
    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

/**
 * Builds the canonical pathname for a (section, entityId) pair. Pure;
 * exported for tests. The entity id is `encodeURIComponent`-ed so
 * reserved characters round-trip safely through `parseSiteEditorPath`.
 */
export function buildSiteEditorPath(
    routeBase: string,
    section: SiteEditorSectionId,
    entityId: string | null = null
): string {
    // Shared with `buildTemplateDeepLink`: an absolute or `javascript:`
    // base would leave the SPA's own origin from inside an `<a href>`.
    const normalizedBase = normalizeRouteBase(routeBase);
    const tail =
        entityId === null
            ? section
            : `${section}/${encodeURIComponent(entityId)}`;

    return `${normalizedBase}/${tail}`;
}

export function useSiteEditorRouting(
    options: UseSiteEditorRoutingOptions
): SiteEditorRouting {
    const { routeBase } = options;

    const [location, setLocation] = useState<SiteEditorLocation>(() => {
        if (typeof window === 'undefined') {
            return { section: DEFAULT_SECTION_ID, entityId: null };
        }

        return parseSiteEditorPath(window.location.pathname, routeBase);
    });

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        function handlePopState(): void {
            setLocation(parseSiteEditorPath(window.location.pathname, routeBase));
        }

        window.addEventListener('popstate', handlePopState);

        return () => {
            window.removeEventListener('popstate', handlePopState);
        };
    }, [routeBase]);

    const navigate = useCallback(
        (
            section: SiteEditorSectionId,
            entityId: string | null = null,
            options: SiteEditorNavigateOptions = {}
        ): void => {
            const next: SiteEditorLocation = { section, entityId };

            setLocation((prev) => {
                if (prev.section === section && prev.entityId === entityId) {
                    return prev;
                }

                return next;
            });

            if (typeof window === 'undefined') {
                return;
            }

            const target = buildSiteEditorPath(routeBase, section, entityId);

            // Avoid pushing duplicate entries when the user clicks the
            // already-active item. A `replace` still runs: the pathname may
            // already match while a query string (a deep link) is stranded
            // in the address bar, and rewriting is exactly what clears it.
            if (window.location.pathname === target && options.replace !== true) {
                return;
            }

            const method = options.replace === true ? 'replaceState' : 'pushState';

            window.history[method]({ section, entityId }, '', target);
        },
        [routeBase]
    );

    return useMemo(
        () => ({
            section: location.section,
            entityId: location.entityId,
            navigate,
        }),
        [location.entityId, location.section, navigate]
    );
}
