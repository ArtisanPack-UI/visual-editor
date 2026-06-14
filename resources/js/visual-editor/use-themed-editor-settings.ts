/**
 * Shared hook that merges the active theme's global-styles presets
 * (palette, font sizes, font families, spacing sizes, gradients) and
 * compiled CSS into the package's default `editorSettings` (#512).
 *
 * Both the post editor (`editor/editor-app.tsx`) and the site editor
 * (`site-editor/block-editor-boundary.tsx`) consume this hook so every
 * editing surface sources its inspector swatches from the host theme's
 * `theme.json` settings rather than the hard-coded `DEFAULT_*` arrays
 * in `editor-settings.ts`. The hard-coded arrays remain as fallbacks
 * when no theme data is available (standalone installs without
 * cms-framework).
 *
 * @package @artisanpack-ui/visual-editor
 * @since   1.0.0
 */

import { useMemo } from 'react';

import { editorSettings } from './editor-settings';
import { useThemeGlobalStylesCss } from './site-editor/use-theme-global-styles-css';
import { useThemeGlobalStylesSettings } from './site-editor/use-theme-global-styles-settings';

interface PaletteEntry {
    slug: string;
    name: string;
    color: string;
}

interface FontSizeEntry {
    slug: string;
    name: string;
    size: string;
}

interface FontFamilyEntry {
    slug: string;
    name: string;
    fontFamily: string;
}

interface SpacingSizeEntry {
    slug: string;
    name: string;
    size: string;
}

interface GradientEntry {
    slug: string;
    name: string;
    gradient: string;
}

export interface UseThemedEditorSettingsOptions {
    apiBase: string | undefined;
    /**
     * Test-only override for the active theme's compiled CSS. In
     * production the hook fetches this through
     * {@see useThemeGlobalStylesCss} keyed on `apiBase`; tests pass an
     * explicit string to avoid hitting the network.
     */
    themeGlobalStylesCss?: string;
    /** Extra properties to merge into the returned settings object. */
    extraSettings?: Record<string, unknown>;
}

/**
 * Pull `settings.color.palette` out of the `/global-styles/base`
 * payload as a normalized `{ slug, name, color }` list. Returns
 * `null` (not an empty array) when the theme didn't define one so
 * callers can keep `editorSettings`'s default palette as a fallback
 * rather than blanking the picker.
 */
export function extractThemePalette(
    settings: Record<string, unknown>,
): readonly PaletteEntry[] | null {
    const palette = (settings.color as { palette?: unknown })?.palette;

    if (!Array.isArray(palette) || palette.length === 0) {
        return null;
    }

    const out: PaletteEntry[] = [];
    const seen = new Set<string>();

    for (const entry of palette) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const color = (entry as { color?: unknown }).color;

        if (typeof slug !== 'string' || slug === '' || typeof color !== 'string') {
            continue;
        }

        if (seen.has(slug)) {
            continue;
        }
        seen.add(slug);

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            color,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

export function extractThemeFontSizes(
    settings: Record<string, unknown>,
): readonly FontSizeEntry[] | null {
    const sizes = (settings.typography as { fontSizes?: unknown })?.fontSizes;

    if (!Array.isArray(sizes) || sizes.length === 0) {
        return null;
    }

    const out: FontSizeEntry[] = [];
    const seen = new Set<string>();

    for (const entry of sizes) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const size = (entry as { size?: unknown }).size;

        if (typeof slug !== 'string' || slug === '' || typeof size !== 'string') {
            continue;
        }

        if (seen.has(slug)) {
            continue;
        }
        seen.add(slug);

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            size,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

export function extractThemeFontFamilies(
    settings: Record<string, unknown>,
): readonly FontFamilyEntry[] | null {
    const families = (settings.typography as { fontFamilies?: unknown })?.fontFamilies;

    if (!Array.isArray(families) || families.length === 0) {
        return null;
    }

    const out: FontFamilyEntry[] = [];
    const seen = new Set<string>();

    for (const entry of families) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const fontFamily = (entry as { fontFamily?: unknown }).fontFamily;

        if (typeof slug !== 'string' || slug === '' || typeof fontFamily !== 'string') {
            continue;
        }

        if (seen.has(slug)) {
            continue;
        }
        seen.add(slug);

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            fontFamily,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

export function extractThemeSpacingSizes(
    settings: Record<string, unknown>,
): readonly SpacingSizeEntry[] | null {
    const sizes = (settings.spacing as { spacingSizes?: unknown })?.spacingSizes;

    if (!Array.isArray(sizes) || sizes.length === 0) {
        return null;
    }

    const out: SpacingSizeEntry[] = [];
    const seen = new Set<string>();

    for (const entry of sizes) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const size = (entry as { size?: unknown }).size;

        if (typeof slug !== 'string' || slug === '' || typeof size !== 'string') {
            continue;
        }

        if (seen.has(slug)) {
            continue;
        }
        seen.add(slug);

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            size,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

export function extractThemeGradients(
    settings: Record<string, unknown>,
): readonly GradientEntry[] | null {
    const gradients = (settings.color as { gradients?: unknown })?.gradients;

    if (!Array.isArray(gradients) || gradients.length === 0) {
        return null;
    }

    const out: GradientEntry[] = [];
    const seen = new Set<string>();

    for (const entry of gradients) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const gradient = (entry as { gradient?: unknown }).gradient;

        if (typeof slug !== 'string' || slug === '' || typeof gradient !== 'string') {
            continue;
        }

        if (seen.has(slug)) {
            continue;
        }
        seen.add(slug);

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            gradient,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

/**
 * Merge the active theme's global-styles presets and CSS into the
 * package's default `editorSettings`. Returns the original
 * `editorSettings` (with any `extraSettings` spread) when no theme
 * data is available — no extra allocation.
 */
export function useThemedEditorSettings(
    options: UseThemedEditorSettingsOptions,
): Record<string, unknown> {
    const { apiBase, themeGlobalStylesCss, extraSettings } = options;

    const fetchedCss = useThemeGlobalStylesCss(apiBase);
    const themeCss = themeGlobalStylesCss !== undefined ? themeGlobalStylesCss : fetchedCss;

    const themeBase = useThemeGlobalStylesSettings(apiBase);

    return useMemo(() => {
        const themeSettings = themeBase?.settings ?? {};
        const themePalette = extractThemePalette(themeSettings);
        const themeFontSizes = extractThemeFontSizes(themeSettings);
        const themeFontFamilies = extractThemeFontFamilies(themeSettings);
        const themeSpacingSizes = extractThemeSpacingSizes(themeSettings);
        const themeGradients = extractThemeGradients(themeSettings);

        // #490 — propagate the theme's color-customization booleans so
        // Gutenberg's `useSettings('color.customGradient')` / `'color.custom'`
        // hooks return the configured value (used by `ColorGradientControl`
        // to decide whether to surface the custom-authoring UI inside the
        // Gradient tab). Without these the auto-injected Background picker
        // shows only the theme palette with no way to author a fresh
        // gradient. `?? true` mirrors WP core's defaults so themes that
        // omit these keys still get the standard authoring affordances.
        const themeColor = ( themeSettings.color as Record<string, unknown> | undefined ) ?? {};
        const themeCustomGradient = 'customGradient' in themeColor
            ? Boolean( themeColor.customGradient )
            : true;
        const themeDefaultGradients = 'defaultGradients' in themeColor
            ? Boolean( themeColor.defaultGradients )
            : true;
        const themeCustomColor = 'custom' in themeColor
            ? Boolean( themeColor.custom )
            : true;
        const themeDefaultPalette = 'defaultPalette' in themeColor
            ? Boolean( themeColor.defaultPalette )
            : true;

        const needsStyles = themeCss !== undefined && themeCss !== '';
        const needsPalette = themePalette !== null;
        const needsFontSizes = themeFontSizes !== null;
        const needsFontFamilies = themeFontFamilies !== null;
        const needsSpacingSizes = themeSpacingSizes !== null;
        const needsGradients = themeGradients !== null;

        if (
            !needsStyles &&
            !needsPalette &&
            !needsFontSizes &&
            !needsFontFamilies &&
            !needsSpacingSizes &&
            !needsGradients
        ) {
            if (extraSettings !== undefined && Object.keys(extraSettings).length > 0) {
                return { ...editorSettings, ...extraSettings };
            }

            return editorSettings;
        }

        const nextStyles = needsStyles
            ? [...editorSettings.styles, { css: themeCss as string }]
            : editorSettings.styles;

        const baseFeatures = editorSettings.__experimentalFeatures ?? {};
        const baseColor = (baseFeatures as { color?: Record<string, unknown> }).color ?? {};
        const baseTypography =
            (baseFeatures as { typography?: Record<string, unknown> }).typography ?? {};
        const baseSpacing =
            (baseFeatures as { spacing?: Record<string, unknown> }).spacing ?? {};

        return {
            ...editorSettings,
            styles: nextStyles,
            ...(extraSettings ?? {}),
            ...(needsPalette ? { colors: themePalette } : {}),
            ...(needsFontSizes ? { fontSizes: themeFontSizes } : {}),
            __experimentalFeatures: {
                ...baseFeatures,
                color: {
                    ...baseColor,
                    // Authoring affordance flags — see comment above the
                    // `themeCustomGradient` extraction in this function for
                    // why these must be forwarded explicitly. WP's
                    // `useSettings` reads these by dotted path; missing
                    // keys make `disableCustomGradients` default to true
                    // and the Gradient tab loses its custom-authoring UI.
                    custom: themeCustomColor,
                    customGradient: themeCustomGradient,
                    defaultPalette: themeDefaultPalette,
                    defaultGradients: themeDefaultGradients,
                    ...(needsPalette || needsGradients
                        ? {
                              palette: needsPalette
                                  ? { theme: themePalette, custom: [] }
                                  : (baseColor as { palette?: unknown }).palette,
                              gradients: needsGradients
                                  ? { theme: themeGradients, custom: [] }
                                  : (baseColor as { gradients?: unknown }).gradients,
                          }
                        : {}),
                },
                typography: {
                    ...baseTypography,
                    ...(needsFontSizes
                        ? {
                              fontSizes: {
                                  ...((baseTypography as { fontSizes?: Record<string, unknown> })
                                      .fontSizes ?? {}),
                                  theme: themeFontSizes,
                              },
                          }
                        : {}),
                    ...(needsFontFamilies
                        ? {
                              fontFamilies: {
                                  ...((baseTypography as { fontFamilies?: Record<string, unknown> })
                                      .fontFamilies ?? {}),
                                  theme: themeFontFamilies,
                              },
                          }
                        : {}),
                },
                spacing: {
                    ...baseSpacing,
                    ...(needsSpacingSizes
                        ? {
                              spacingSizes: {
                                  ...((baseSpacing as { spacingSizes?: Record<string, unknown> })
                                      .spacingSizes ?? {}),
                                  theme: themeSpacingSizes,
                              },
                          }
                        : {}),
                },
            },
        };
    }, [themeCss, themeBase, extraSettings]);
}
