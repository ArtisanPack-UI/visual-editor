/**
 * Keystone #47 — `useThemeGlobalStylesCss` is the canvas's bridge to
 * the active theme's compiled global-styles CSS. These tests pin the
 * three states the boundary depends on:
 *
 *   1. No apiBase → settle synchronously to `''` so the boundary
 *      renders with just `DEFAULT_CANVAS_STYLES`.
 *   2. apiBase given → fetch on mount, settle to the resolved string.
 *   3. Module-level cache de-duplicates concurrent mounts so a
 *      remount inside the same SPA session doesn't re-hit the network.
 */

import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

vi.mock('../styles/global-styles-api', () => ({
    fetchGlobalStylesCss: vi.fn(),
}));

// Drive the fonts-changed channel directly so a "font installed" event can be
// fired mid-test without touching the real store or the network.
const { fontsChangedListeners } = vi.hoisted(() => ({
    fontsChangedListeners: new Set<() => void>(),
}));

vi.mock('../../fonts/installed-fonts-store', () => ({
    subscribeFontsChanged: (listener: () => void) => {
        fontsChangedListeners.add(listener);

        return () => {
            fontsChangedListeners.delete(listener);
        };
    },
}));

function emitFontsChanged(): void {
    fontsChangedListeners.forEach((listener) => listener());
}

import { fetchGlobalStylesCss as fetchGlobalStylesCssRaw } from '../styles/global-styles-api';

const FETCH_GLOBAL_STYLES_CSS = vi.mocked(fetchGlobalStylesCssRaw);

import {
    resetThemeGlobalStylesCssCache,
    useThemeGlobalStylesCss,
} from '../use-theme-global-styles-css';

// Importing the hook module registered its module-scope invalidation
// subscriber (the one that clears the cache on any font change, regardless of
// what is mounted). Snapshot it so `beforeEach` can drop per-test hook
// listeners without also dropping the module-scope one.
const MODULE_LISTENERS = new Set(fontsChangedListeners);

describe('useThemeGlobalStylesCss', () => {
    beforeEach(() => {
        FETCH_GLOBAL_STYLES_CSS.mockReset();
        resetThemeGlobalStylesCssCache();
        fontsChangedListeners.clear();
        MODULE_LISTENERS.forEach((listener) => fontsChangedListeners.add(listener));
    });

    it('settles to "" synchronously when no apiBase is given', () => {
        const { result } = renderHook(() => useThemeGlobalStylesCss(undefined));

        expect(result.current).toBe('');
        expect(FETCH_GLOBAL_STYLES_CSS).not.toHaveBeenCalled();
    });

    it('fetches once on mount and surfaces the resolved CSS', async () => {
        const css = ':root { --wp--preset--color--primary: #0f172a; }';
        FETCH_GLOBAL_STYLES_CSS.mockResolvedValue(css);

        const { result } = renderHook(() =>
            useThemeGlobalStylesCss('/visual-editor/api')
        );

        // Before the fetch resolves the hook returns `undefined` so
        // the boundary keeps rendering the package's default canvas
        // baseline without partial styles.
        expect(result.current).toBeUndefined();

        await act(async () => {
            // Flush microtasks so the .then() inside the hook fires.
        });

        expect(result.current).toBe(css);
        expect(FETCH_GLOBAL_STYLES_CSS).toHaveBeenCalledTimes(1);
        expect(FETCH_GLOBAL_STYLES_CSS).toHaveBeenCalledWith({
            apiBase: '/visual-editor/api',
        });
    });

    it('reuses the cached fetch across remounts for the same apiBase', async () => {
        FETCH_GLOBAL_STYLES_CSS.mockResolvedValue('/*cached*/');

        const first = renderHook(() =>
            useThemeGlobalStylesCss('/visual-editor/api')
        );

        await act(async () => {});

        first.unmount();

        renderHook(() => useThemeGlobalStylesCss('/visual-editor/api'));

        await act(async () => {});

        // One fetch total — the cache short-circuits the second mount.
        expect(FETCH_GLOBAL_STYLES_CSS).toHaveBeenCalledTimes(1);
    });

    it('does not let an obsolete request repopulate the cache after a font mutation', async () => {
        let resolveStale: (value: string) => void = () => {};
        const stalePromise = new Promise<string>((resolve) => {
            resolveStale = resolve;
        });
        let resolveFresh: (value: string) => void = () => {};
        const freshPromise = new Promise<string>((resolve) => {
            resolveFresh = resolve;
        });

        FETCH_GLOBAL_STYLES_CSS.mockReturnValueOnce(stalePromise).mockReturnValueOnce(
            freshPromise
        );

        const view = renderHook(() =>
            useThemeGlobalStylesCss('/visual-editor/api')
        );
        expect(view.result.current).toBeUndefined();

        // A font install invalidates the in-flight (pre-mutation) request and
        // triggers a refetch.
        act(() => {
            emitFontsChanged();
        });

        // The fresh request resolves first…
        await act(async () => {
            resolveFresh('/*FRESH*/');
        });
        expect(view.result.current).toBe('/*FRESH*/');

        // …then the obsolete request resolves last. It must not clobber the
        // cache with the pre-mutation CSS.
        await act(async () => {
            resolveStale('/*STALE*/');
        });
        expect(view.result.current).toBe('/*FRESH*/');

        view.unmount();

        // A remount reads the cache synchronously — it must get the fresh CSS,
        // proving the obsolete request never overwrote the entry.
        const remount = renderHook(() =>
            useThemeGlobalStylesCss('/visual-editor/api')
        );
        expect(remount.result.current).toBe('/*FRESH*/');
        expect(FETCH_GLOBAL_STYLES_CSS).toHaveBeenCalledTimes(2);
    });

    it('clears the cache on a font mutation with no consumer mounted (away-flow)', async () => {
        FETCH_GLOBAL_STYLES_CSS.mockResolvedValueOnce('/*before*/').mockResolvedValueOnce(
            '/*after*/'
        );

        // Visit a canvas (e.g. Templates): the CSS resolves and caches.
        const first = renderHook(() => useThemeGlobalStylesCss('/visual-editor/api'));
        await act(async () => {});
        expect(first.result.current).toBe('/*before*/');
        first.unmount();

        // Navigate to the Styles panel — which mounts no canvas consumer of
        // this hook — and install a font. The module-scope subscription must
        // still clear the cache with nothing mounted.
        act(() => {
            emitFontsChanged();
        });

        // Back to a canvas: the now-empty cache forces a fresh fetch of the
        // regenerated stylesheet rather than serving the pre-install CSS.
        const second = renderHook(() => useThemeGlobalStylesCss('/visual-editor/api'));
        await act(async () => {});
        expect(second.result.current).toBe('/*after*/');
        expect(FETCH_GLOBAL_STYLES_CSS).toHaveBeenCalledTimes(2);
    });

    it('returns the resolved CSS synchronously from useState on remount-after-resolve', async () => {
        const css = ':root { --wp--preset--color--primary: #0f172a; }';
        FETCH_GLOBAL_STYLES_CSS.mockResolvedValue(css);

        const first = renderHook(() =>
            useThemeGlobalStylesCss('/visual-editor/api')
        );

        // Wait for the fetch to settle so the cache entry transitions
        // from `pending` to `resolved`.
        await act(async () => {});
        expect(first.result.current).toBe(css);

        first.unmount();

        // The remount must return the cached value on its FIRST
        // render — no transient `undefined`, no flash of unstyled
        // canvas (CodeRabbit on PR #456).
        const second = renderHook(() =>
            useThemeGlobalStylesCss('/visual-editor/api')
        );

        expect(second.result.current).toBe(css);
    });
});
