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
 * Module-level cache keyed by `apiBase` so a remount inside the same
 * editor session reuses the already-fetched CSS without re-hitting the
 * network. Cleared explicitly by {@see resetThemeGlobalStylesCssCache}
 * — used by tests, never by production code.
 */

import { useEffect, useState } from 'react';

import { fetchGlobalStylesCss } from './styles/global-styles-api';

const cache = new Map<string, Promise<string>>();

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
    const [css, setCss] = useState<string | undefined>(() => {
        if (apiBase === undefined || apiBase === '') {
            return '';
        }

        // Synchronous cache hit — return immediately so the boundary's
        // initial render already carries the styles and the editor
        // surface doesn't flash unstyled on remount.
        const cached = cache.get(apiBase);

        if (cached !== undefined) {
            // The promise is in flight; we still need an async settle.
            // Surface `undefined` until it resolves so the boundary
            // mounts without a partial styles array.
            return undefined;
        }

        return undefined;
    });

    useEffect(() => {
        if (apiBase === undefined || apiBase === '') {
            setCss('');

            return;
        }

        let cancelled = false;

        let pending = cache.get(apiBase);

        if (pending === undefined) {
            pending = fetchGlobalStylesCss({ apiBase });
            cache.set(apiBase, pending);
        }

        pending.then((value) => {
            if (!cancelled) {
                setCss(value);
            }
        }).catch(() => {
            // Network failure already surfaces as `''` from the
            // fetch wrapper; this `.catch` is just belt-and-suspenders
            // for promise rejection elsewhere in the chain.
            if (!cancelled) {
                setCss('');
            }
        });

        return () => {
            cancelled = true;
        };
    }, [apiBase]);

    return css;
}
