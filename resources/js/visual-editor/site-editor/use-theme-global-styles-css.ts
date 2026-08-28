/**
 * Hook that fetches the active theme's compiled global-styles CSS once
 * per `apiBase`, ready to append to the editor canvas's `settings.styles`
 * array (Keystone #47).
 *
 * The CSS comes from cms-framework's `GlobalStylesEmitter` via the
 * `/visual-editor/api/global-styles/css` endpoint. It's the same payload
 * the public front-end emits through the renderer-blade Blade
 * components — fetching it here is the canvas's half of the parity fix.
 *
 * The hook returns `undefined` while the fetch is in flight, an empty
 * string when no theme styles are available (no active theme, or
 * cms-framework not installed), and the full CSS body once loaded. The
 * boundary treats `undefined` and `''` the same way: render with just
 * `DEFAULT_CANVAS_STYLES`. The CSS only appends after it arrives, so a
 * slow fetch never blocks the editor boot.
 *
 * Module-level cache keyed by `apiBase`. Stores either a pending
 * `Promise<string>` (first mount, fetch in flight) or the resolved
 * `string` (subsequent mounts, fetch settled) so a remount inside the
 * same editor session returns the CSS synchronously from `useState`'s
 * lazy initializer — no transient `undefined` render, no flash of
 * unstyled canvas (per CodeRabbit on PR #456). Cleared explicitly by
 * {@see resetThemeGlobalStylesCssCache} — used by tests only.
 */

import { useEffect, useState } from 'react';

import { subscribeFontsChanged } from '../fonts/installed-fonts-store';
import { fetchGlobalStylesCss } from './styles/global-styles-api';

type CachedCss =
    | { status: 'pending'; promise: Promise<string> }
    | { status: 'resolved'; value: string };

const cache = new Map<string, CachedCss>();

// Invalidate the cache on any font mutation at *module* scope, not inside the
// hook's effect. The Font Library button lives on the site-editor Styles
// panel, which mounts no canvas consumer of this hook — so an install there
// would never reach an in-effect subscription, and returning to a canvas
// would hand the pre-install CSS back synchronously from the lazy `useState`
// initializer, leaving the new `@font-face` missing until a full reload. A
// module-scope subscription clears the entry regardless of what is mounted;
// mounted consumers additionally bump a per-hook token (below) to re-fetch.
subscribeFontsChanged(() => {
    cache.clear();
});

/**
 * Test-only cache reset. Production code lets the cache live for the
 * duration of the SPA — there's no scenario where the active theme
 * changes mid-session in the same shell mount.
 */
export function resetThemeGlobalStylesCssCache(): void {
    cache.clear();
}

export function useThemeGlobalStylesCss(
    apiBase: string | undefined
): string | undefined {
    // Bumped whenever a font is installed or uninstalled so the effect below
    // re-fetches the canvas stylesheet — the `/global-styles/css` payload folds
    // in the regenerated `fonts.css`, so a bump re-references installed fonts in
    // the iframe and keeps previews accurate.
    const [refetchToken, setRefetchToken] = useState(0);

    const [css, setCss] = useState<string | undefined>(() => {
        if (apiBase === undefined || apiBase === '') {
            return '';
        }

        const cached = cache.get(apiBase);

        // Remount-after-resolved: hand the resolved string back
        // synchronously from the lazy initializer so the boundary's
        // first render already carries the styles and the canvas
        // doesn't flash unstyled.
        if (cached?.status === 'resolved') {
            return cached.value;
        }

        // Either no entry yet or a still-in-flight fetch from another
        // mount — let the effect settle the value asynchronously.
        return undefined;
    });

    useEffect(() => {
        if (apiBase === undefined || apiBase === '') {
            setCss('');

            return;
        }

        let cancelled = false;
        let entry = cache.get(apiBase);

        if (entry === undefined) {
            const promise = fetchGlobalStylesCss({ apiBase });
            entry = { status: 'pending', promise };
            cache.set(apiBase, entry);

            // Upgrade the entry to `resolved` once the network settles
            // so future remounts can return the value synchronously.
            // The upgrade is independent of any specific mount's
            // lifecycle — even if every consumer unmounts before the
            // fetch resolves, the next mount still gets the cached
            // value without re-hitting the network.
            //
            // Guard on the entry identity: a font mutation invalidates the
            // cache (via `cache.delete` below) while this request may still
            // be in flight. Only promote a result when this pending entry is
            // still the current cache entry, so an obsolete request can't
            // repopulate the cache with pre-mutation CSS and make the refetch
            // effect short-circuit on stale data.
            const pendingEntry = entry;
            promise.then(
                (value) => {
                    if (cache.get(apiBase) === pendingEntry) {
                        cache.set(apiBase, { status: 'resolved', value });
                    }
                },
                () => {
                    // Treat network failure as an empty stylesheet so
                    // the canvas falls back to its package defaults
                    // and the next remount doesn't re-hit a known-bad
                    // endpoint. {@link fetchGlobalStylesCss} swallows
                    // most errors already; this is belt-and-suspenders.
                    if (cache.get(apiBase) === pendingEntry) {
                        cache.set(apiBase, { status: 'resolved', value: '' });
                    }
                }
            );
        }

        // `entry` may already be 'resolved' on remount — short-circuit
        // to a sync update so React batches it with the initial render.
        if (entry.status === 'resolved') {
            setCss(entry.value);

            return () => {
                cancelled = true;
            };
        }

        entry.promise.then(
            (value) => {
                if (!cancelled) {
                    setCss(value);
                }
            },
            () => {
                if (!cancelled) {
                    setCss('');
                }
            }
        );

        return () => {
            cancelled = true;
        };
    }, [apiBase, refetchToken]);

    // Re-reference the canvas stylesheet after a font install/uninstall. The
    // module-scope subscription above has already cleared the cache; this
    // per-hook subscription only bumps the refetch token so a *mounted*
    // consumer's effect re-runs against the now-empty cache and re-fetches
    // the regenerated `fonts.css`.
    useEffect(() => {
        return subscribeFontsChanged(() => {
            setRefetchToken((token) => token + 1);
        });
    }, [apiBase]);

    return css;
}
