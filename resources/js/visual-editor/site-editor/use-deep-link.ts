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
 * once, and the router keeps its single id-shaped contract. The landing
 * navigate replaces the current history entry, so the resolved canonical
 * URL is what the user ends up with in their address bar — without a dead
 * query-string entry sitting behind the Back button.
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
import type { SiteEditorNavigateOptions } from './use-site-editor-routing';

export interface UseSiteEditorDeepLinkOptions {
    apiConfig: SiteEditorApiConfig;
    /** The routing instance's `navigate` — same signature, same push semantics. */
    navigate: (
        section: SiteEditorSectionId,
        entityId?: string | null,
        options?: SiteEditorNavigateOptions
    ) => void;
    /**
     * Query string to parse. Defaults to `window.location.search`; tests
     * (and any SSR host) pass their own rather than mutating `location`.
     */
    search?: string;
}

/**
 * Slug whose not-found toast has already been announced. Module-level so
 * StrictMode's second invocation of the same mount is deduped, and cleared
 * on the next macrotask so it only spans that window — see `landOnIndex`.
 */
let announcedFor: string | null = null;

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

        // The lookup is only cancelled on unmount, so a user who browses
        // within the SPA while it is in flight would otherwise get yanked
        // to the deep-link target by the late resolution.
        //
        // The search string is compared alongside the pathname because the
        // pathname alone can round-trip: navigating away and back in-SPA
        // while the slug lookup is in flight restores it, and a late
        // resolution would then teleport the user after all. The deep-link
        // query still being present is the real "nothing has happened yet"
        // signal — both `landOnEntity` and `landOnIndex` clear it via a
        // `replace`, and so does any other in-SPA navigation.
        const initialPathname =
            typeof window === 'undefined' ? '' : window.location.pathname;
        const initialSearch =
            typeof window === 'undefined' ? '' : window.location.search;
        const userHasNavigated = (): boolean =>
            typeof window !== 'undefined' &&
            (window.location.pathname !== initialPathname ||
                window.location.search !== initialSearch);

        function landOnIndex(): void {
            if (cancelled || userHasNavigated()) {
                return;
            }

            // `replace`, not push: this landing rewrites the entry the user
            // arrived on. Pushing would leave Back pointing at the
            // query-string URL, which `handlePopState` reads pathname-only
            // and the mount-once hook never re-resolves — address bar and
            // UI would disagree with no way back into sync.
            navigateRef.current('templates', null, { replace: true });

            // StrictMode double-invokes the effect, and the duplicate
            // navigation is absorbed by the routing dedupe — the toast is
            // not, so it announces the same failure twice. The routing
            // dedupe has no equivalent here, hence the module-level latch.
            if (announcedFor === request.slug) {
                return;
            }

            announcedFor = request.slug;

            // Released on the next macrotask, once the double-invocation
            // window has passed. Holding it for the page's lifetime would
            // silently swallow the toast on a later remount, leaving the
            // author on the templates index with no explanation — and would
            // leak between tests in a file.
            setTimeout(() => {
                if (announcedFor === request.slug) {
                    announcedFor = null;
                }
            }, 0);

            toastRef.current.warning(
                sprintf(
                    /* translators: %s: requested template slug. */
                    __('The template “%s” was not found.', TEXT_DOMAIN),
                    request.slug
                )
            );
        }

        function landOnEntity(entityId: string): void {
            if (cancelled || userHasNavigated()) {
                return;
            }

            navigateRef.current('templates', entityId, { replace: true });
        }

        async function resolve(): Promise<void> {
            // A caller that already knows the id skips the lookup — the
            // reserved `entity_id` path (see `deep-link.ts`). Only for an
            // id-shaped value: a hand-typed `entity_id=oops` would
            // otherwise skip verification and land the author on an
            // entity-editor fetch failure instead of the not-found toast.
            if (request.entityId !== null && /^\d+$/.test(request.entityId)) {
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
