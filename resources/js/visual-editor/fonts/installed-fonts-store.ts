/**
 * Installed-fonts store slice (#636).
 *
 * A tiny framework-agnostic pub/sub store that holds the list of installed
 * fonts so any editor surface — most importantly the block typography panel's
 * {@link FontFamilyPicker} — can render installed families as font-family
 * options without re-fetching per mount. It lives outside React so the Font
 * Library modal (which owns its own {@link fontLibraryReducer}) can announce
 * install/uninstall mutations to every mounted picker at once.
 *
 * The store exposes each installed font as a `{ slug, label, value }` option
 * whose `value` is the same `var(--wp--preset--font-family--{slug})` custom
 * property that {@link \ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator}
 * emits into `fonts.css`. Selecting one therefore applies through the existing
 * block style pipeline exactly like a theme.json font preset — no block schema
 * change — and resolves against the generated `:root` custom properties on both
 * the editor canvas and the public site.
 *
 * Two subscriber channels are deliberately separate:
 *   - the snapshot channel ({@link subscribeInstalledFonts}) drives React
 *     re-renders through {@link useInstalledFontOptions} and fires on every
 *     state change, including the initial load;
 *   - the mutation channel ({@link subscribeFontsChanged}) fires only when a
 *     font is actually installed or uninstalled, so the canvas can re-reference
 *     `fonts.css` without a needless refetch on first load.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.7.0
 */

import { useEffect, useMemo, useSyncExternalStore } from 'react';

import { fetchInstalledFonts, type InstalledFont } from './api-client';
import type { LoadStatus } from './store';

/** A font-family option, shaped to merge with the theme preset options. */
export interface FontFamilyOption {
    readonly slug: string;
    readonly label: string;
    readonly value: string;
}

interface InstalledFontsState {
    readonly status: LoadStatus;
    readonly fonts: readonly InstalledFont[];
    readonly error: string | null;
}

const initialState: InstalledFontsState = {
    status: 'idle',
    fonts: [],
    error: null,
};

let state: InstalledFontsState = initialState;

/** Snapshot subscribers — drive React re-renders on any state change. */
const snapshotListeners = new Set<() => void>();

/** Mutation subscribers — fire only on install/uninstall, for the canvas refresh. */
const mutationListeners = new Set<() => void>();

/** The in-flight load promise, so concurrent callers share one request. */
let inFlight: Promise<void> | null = null;

/**
 * Set when a forced reload is requested while a load is already in flight. The
 * running fetch schedules exactly one more fetch as it settles, so a font
 * installed or uninstalled mid-load is never lost to request coalescing.
 */
let pendingForce = false;

function setState(next: InstalledFontsState): void {
    state = next;
    snapshotListeners.forEach((listener) => listener());
}

function emitFontsChanged(): void {
    mutationListeners.forEach((listener) => listener());
}

/**
 * Normalize a stored font slug into the CSS custom-property token so the option
 * value lines up with the `--wp--preset--font-family--{slug}` declaration in
 * `fonts.css`. The input is the already-slugged `font.slug` column (ASCII,
 * dash-separated), so this lowercase + `[^a-z0-9]+`→`-` collapse is idempotent
 * with the server's `Str::slug` in `FontsCssGenerator::presetSlug()`; it does
 * not re-implement `Str::slug`'s transliteration, which the pre-slugged input
 * never needs.
 *
 * @since 1.7.0
 */
export function fontPresetSlug(slug: string): string {
    return slug
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * The preset custom-property reference for an installed font's slug.
 *
 * @since 1.7.0
 */
export function fontPresetValue(slug: string): string {
    return `var(--wp--preset--font-family--${fontPresetSlug(slug)})`;
}

/**
 * Map an installed font to a font-family option. Exposed for tests and reuse.
 *
 * @since 1.7.0
 */
export function installedFontToOption(font: InstalledFont): FontFamilyOption {
    return {
        slug: fontPresetSlug(font.slug),
        label: font.family,
        value: fontPresetValue(font.slug),
    };
}

/**
 * Subscribe to snapshot changes. Used by {@link useInstalledFontOptions}.
 *
 * @since 1.7.0
 */
export function subscribeInstalledFonts(listener: () => void): () => void {
    snapshotListeners.add(listener);

    return () => {
        snapshotListeners.delete(listener);
    };
}

/**
 * Subscribe to install/uninstall mutations. Used by the canvas to re-reference
 * the regenerated `fonts.css` so previews stay accurate.
 *
 * @since 1.7.0
 */
export function subscribeFontsChanged(listener: () => void): () => void {
    mutationListeners.add(listener);

    return () => {
        mutationListeners.delete(listener);
    };
}

/**
 * The current store snapshot. Stable identity between changes so
 * `useSyncExternalStore` can bail out of re-renders.
 *
 * @since 1.7.0
 */
export function getInstalledFontsSnapshot(): InstalledFontsState {
    return state;
}

/**
 * Fetch the installed-fonts list into the store. Concurrent calls share a
 * single request; a settled store is only re-fetched when `force` is set.
 * Failures land as an empty list in the `error` status so pickers simply fall
 * back to the theme presets rather than throwing.
 *
 * @since 1.7.0
 */
export function loadInstalledFonts(
    options: { readonly force?: boolean } = {}
): Promise<void> {
    const force = options.force ?? false;

    if (inFlight !== null) {
        // A load is already running. A forced caller (install/uninstall) can't
        // reuse it — it may have been issued before the mutation committed — so
        // flag a follow-up fetch rather than coalescing into the stale request.
        if (force) {
            pendingForce = true;
        }

        return inFlight;
    }

    if (!force && state.status === 'ready') {
        return Promise.resolve();
    }

    inFlight = runInstalledFontsFetch();

    return inFlight;
}

/**
 * Issue the fetch and fold the result into the store. On settle, run once more
 * when a force was requested mid-flight so a mutation during a load isn't lost.
 *
 * @since 1.7.0
 */
function runInstalledFontsFetch(): Promise<void> {
    setState({ ...state, status: 'loading', error: null });

    return fetchInstalledFonts()
        .then((result) => {
            setState({ status: 'ready', fonts: result.fonts, error: null });
        })
        .catch((error: unknown) => {
            const message =
                error instanceof Error ? error.message : 'Failed to load fonts.';
            setState({ status: 'error', fonts: [], error: message });
        })
        .finally(() => {
            if (pendingForce) {
                pendingForce = false;
                inFlight = runInstalledFontsFetch();
            } else {
                inFlight = null;
            }
        });
}

/**
 * Trigger a one-time load if the store has never been populated. Safe to call
 * from every picker mount.
 *
 * @since 1.7.0
 */
export function ensureInstalledFontsLoaded(): void {
    if (state.status === 'idle') {
        void loadInstalledFonts();
    }
}

/**
 * Announce that a font was installed or uninstalled: reload the authoritative
 * list from the server (updating every mounted picker) and fire the mutation
 * channel so the canvas re-references the regenerated `fonts.css`.
 *
 * @since 1.7.0
 */
export function notifyFontsChanged(): void {
    void loadInstalledFonts({ force: true });
    emitFontsChanged();
}

/**
 * Replace the installed-fonts list directly, without a network round-trip.
 * Used to hydrate the store from a caller that already holds the list; does not
 * fire the mutation channel.
 *
 * @since 1.7.0
 */
export function setInstalledFonts(fonts: readonly InstalledFont[]): void {
    setState({ status: 'ready', fonts, error: null });
}

/**
 * Test-only reset of the store back to its initial, unloaded state.
 *
 * @since 1.7.0
 */
export function resetInstalledFontsStore(): void {
    inFlight = null;
    pendingForce = false;
    state = initialState;
    snapshotListeners.forEach((listener) => listener());
}

/**
 * React hook returning the raw installed-fonts list, triggering a one-time load
 * on mount. Returns a stable array between store changes.
 *
 * @since 1.7.0
 */
export function useInstalledFonts(): readonly InstalledFont[] {
    const snapshot = useSyncExternalStore(
        subscribeInstalledFonts,
        getInstalledFontsSnapshot,
        getInstalledFontsSnapshot
    );

    useEffect(() => {
        ensureInstalledFontsLoaded();
    }, []);

    return snapshot.fonts;
}

/**
 * React hook returning the installed fonts as font-family options, triggering a
 * one-time load on mount. Returns a stable array between store changes.
 *
 * @since 1.7.0
 */
export function useInstalledFontOptions(): readonly FontFamilyOption[] {
    const fonts = useInstalledFonts();

    return useMemo(() => fonts.map(installedFontToOption), [fonts]);
}
