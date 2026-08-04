/**
 * On-mount deep-link resolution for the site editor (#625).
 *
 * Reads `?entity=…&slug=…` off `location.search` once, resolves the slug
 * to an entity id through the list endpoint, and pushes the SPA's router
 * at the matching editor view. The whole thing is additive: with no
 * query string the hook returns before it touches the network and the
 * SPA lands exactly where it does today.
 *
 * ## Why a lookup rather than a slug route
 *
 * `useSiteEditorRouting` addresses entities by id, and the applied-template
 * endpoint the composed view reads (#623) returns a slug and no id. Rather
 * than teach the router a second addressing mode — and have every entity
 * view guess which one it was handed — the slug is resolved to an id here,
 * once, and the router keeps its single id-shaped contract. The pushState
 * the navigate performs also drops the query string, so the resolved
 * canonical URL is what the user ends up with in their address bar.
 *
 * ## Failure is a landing, not a crash
 *
 * An unresolvable slug lands on the Templates index with a toast naming
 * the slug. A failed lookup does the same: an author who followed an
 * **Edit template ↗** link wants to be in the site editor either way, and
 * the templates list is one click from wherever they were headed.
 *
 * @since 1.6.0
 */

import { useEffect, useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useToast } from '@artisanpack-ui/react/feedback';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { listEntities, type SiteEditorApiConfig } from './api-client';
import { parseDeepLink } from './deep-link';
import type { SiteEditorSectionId } from './sections';

export interface UseSiteEditorDeepLinkOptions {
    apiConfig: SiteEditorApiConfig;
    /** The routing instance's `navigate` — same signature, same push semantics. */
    navigate: (section: SiteEditorSectionId, entityId?: string | null) => void;
    /**
     * Query string to parse. Defaults to `window.location.search`; tests
     * (and any SSR host) pass their own rather than mutating `location`.
     */
    search?: string;
}

/**
 * Resolve the mount-time deep link, if there is one. Fires at most once
 * per mount.
 *
 * Every mutable input is read through a ref so the effect can carry an
 * empty dependency array: a re-render that changes `navigate`'s identity
 * must not re-navigate the user away from wherever they have since
 * browsed. A ref *latch* would do the same job for re-renders but would
 * turn a double-invoked effect (React StrictMode, or a remount in place)
 * into a mount that never navigates at all, which is a far worse failure
 * than navigating twice to the same destination.
 */
export function useSiteEditorDeepLink(
    options: UseSiteEditorDeepLinkOptions
): void {
    const { apiConfig, navigate, search } = options;
    const toast = useToast();

    const navigateRef = useRef(navigate);
    navigateRef.current = navigate;
    const toastRef = useRef(toast);
    toastRef.current = toast;
    const apiConfigRef = useRef(apiConfig);
    apiConfigRef.current = apiConfig;
    const searchRef = useRef(search);
    searchRef.current = search;

    useEffect(() => {
        const rawSearch =
            searchRef.current ??
            (typeof window === 'undefined' ? '' : window.location.search);
        const parsed = parseDeepLink(rawSearch);

        if (parsed === null) {
            return;
        }

        const request = parsed;
        let cancelled = false;

        function landOnIndex(): void {
            if (cancelled) {
                return;
            }

            navigateRef.current('templates', null);
            toastRef.current.warning(
                sprintf(
                    /* translators: %s: requested template slug. */
                    __('The template “%s” was not found.', TEXT_DOMAIN),
                    request.slug
                )
            );
        }

        function landOnEntity(entityId: string): void {
            if (cancelled) {
                return;
            }

            navigateRef.current('templates', entityId);
        }

        async function resolve(): Promise<void> {
            // A caller that already knows the id skips the lookup — the
            // reserved `entity_id` path (see `deep-link.ts`).
            if (request.entityId !== null) {
                landOnEntity(request.entityId);

                return;
            }

            try {
                const matches = await listEntities(
                    apiConfigRef.current,
                    'template',
                    { slug: request.slug }
                );

                // The list endpoint filters server-side, but a host that
                // ignores the `slug` param would hand back every template.
                // Match explicitly rather than trusting `matches[0]`.
                const match = matches.find(
                    (record) => record.slug === request.slug
                );

                if (match === undefined) {
                    landOnIndex();

                    return;
                }

                landOnEntity(String(match.id));
            } catch {
                landOnIndex();
            }
        }

        void resolve();

        return (): void => {
            cancelled = true;
        };
        // Mount-only by design — every mutable input is read through the
        // refs above, so an empty dependency array is correct rather than
        // a lint workaround.
    }, []);
}
