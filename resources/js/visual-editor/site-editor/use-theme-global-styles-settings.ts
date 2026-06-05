/**
 * Hook that fetches the active theme's `settings` payload from
 * `/global-styles/base` once per `apiBase` and returns it ready to
 * merge into the editor's `__experimentalFeatures` (Keystone #53).
 *
 * Why this exists: the inspector's color/font-size pickers render
 * swatches from the slugs in `__experimentalFeatures.color.palette`,
 * and cms-framework's `GlobalStylesEmitter` binds those same slugs
 * via `.has-{slug}-*` CSS rules. If the editor uses a hard-coded
 * default palette while the emitter binds the theme.json palette,
 * the two palettes don't share slugs — picker choices light up the
 * swatch but no CSS rule applies the color. This hook closes that
 * gap by sourcing the same palette from the same place both sides
 * read from.
 *
 * The hook returns `undefined` while the fetch is in flight, an
 * empty object when no theme is active or cms-framework isn't
 * installed, and the parsed `settings` once loaded. The boundary
 * treats `undefined` / `{}` the same way: fall back to
 * `editorSettings` defaults.
 *
 * Module-level cache keyed by `apiBase` matches
 * {@link useThemeGlobalStylesCss}'s pattern so a remount inside
 * the same editor session resolves synchronously.
 */

import { useEffect, useState } from 'react';

import { fetchGlobalStylesBase, type GlobalStylesBase } from './styles/global-styles-api';

export interface GlobalStylesBasePayload {
    settings: Record<string, unknown>;
    styles: Record<string, unknown>;
}

const EMPTY: GlobalStylesBasePayload = { settings: {}, styles: {} };

/**
 * Wraps {@link fetchGlobalStylesBase}'s throw-on-failure contract in
 * the empty-payload-on-failure contract this hook expects, so a
 * missing endpoint / network blip falls back to package defaults
 * instead of cascading into the editor mount as an uncaught error.
 */
function safeFetch(apiBase: string): Promise<GlobalStylesBasePayload> {
    return fetchGlobalStylesBase({ apiBase })
        .then((base: GlobalStylesBase) => ({
            settings: base.settings ?? {},
            styles: base.styles ?? {},
        }))
        .catch(() => EMPTY);
}

type CachedSettings =
    | { status: 'pending'; promise: Promise<GlobalStylesBasePayload> }
    | { status: 'resolved'; value: GlobalStylesBasePayload };

const cache = new Map<string, CachedSettings>();

export function resetThemeGlobalStylesSettingsCache(): void {
    cache.clear();
}

export function useThemeGlobalStylesSettings(
    apiBase: string | undefined
): GlobalStylesBasePayload | undefined {
    const [value, setValue] = useState<GlobalStylesBasePayload | undefined>(() => {
        if (apiBase === undefined || apiBase === '') {
            return EMPTY;
        }

        const cached = cache.get(apiBase);

        if (cached?.status === 'resolved') {
            return cached.value;
        }

        return undefined;
    });

    useEffect(() => {
        if (apiBase === undefined || apiBase === '') {
            setValue(EMPTY);

            return;
        }

        let cancelled = false;
        let entry = cache.get(apiBase);

        if (entry === undefined) {
            const promise = safeFetch(apiBase);
            entry = { status: 'pending', promise };
            cache.set(apiBase, entry);

            promise.then((resolved) => {
                cache.set(apiBase, { status: 'resolved', value: resolved });
            });
        }

        if (entry.status === 'resolved') {
            setValue(entry.value);

            return () => {
                cancelled = true;
            };
        }

        entry.promise.then((resolved) => {
            if (!cancelled) {
                setValue(resolved);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [apiBase]);

    return value;
}
