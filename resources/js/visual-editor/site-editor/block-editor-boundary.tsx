/**
 * Shared `BlockEditorProvider` boundary for the site editor — #436.
 *
 * `@wordpress/block-editor` scopes its data store per
 * `<BlockEditorProvider>` instance. The site-editor sections used to
 * mount the provider *inside* their canvas component
 * (`EntityEditorCanvas`, `PatternCanvas`), so the inspector — rendered
 * as a sibling of the canvas — fell outside the provider's registry
 * scope and `select('core/block-editor').getSelectedBlockClientId()`
 * always read null. The Block tab of the inspector was permanently
 * stuck on "Click on a block to view its settings."
 *
 * Hoisting the provider here, above *both* the canvas and the
 * inspector slots, gives them a single shared registry — matching how
 * the post editor (`editor/editor-app.tsx`) has always wired it. React
 * portals preserve context, so the lazy sections (patterns) can keep
 * portaling their canvas and inspector into separate DOM slots as long
 * as both portals are React-descendants of this boundary.
 *
 * `Popover.Slot` and `ConvertToPatternControl` live inside the
 * provider — they were previously inside each canvas's own provider;
 * they move here with it.
 */

import {
    BlockEditorProvider,
} from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { select as wpSelect } from '@wordpress/data';
import { useMemo } from 'react';
// `@wordpress/format-library` is a side-effect import: it registers the
// core rich-text formats (bold, italic, link, …) so the block toolbar's
// inline formatting controls work inside RichText blocks. It lived in
// the canvas components before the provider was hoisted; it follows the
// provider here.
import '@wordpress/format-library';
import { type ReactNode } from 'react';

import { ConvertToPatternControl } from '../editor/convert-to-pattern-control';
import { editorSettings } from '../editor-settings';
import { useThemeGlobalStylesCss } from './use-theme-global-styles-css';
import { useThemeGlobalStylesSettings } from './use-theme-global-styles-settings';

// Gutenberg editor-surface stylesheets. Previously imported by each
// canvas component; they follow the provider here so the boundary owns
// the editor surface end-to-end and the canvas components stay style-
// free presentational shells.
import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

export interface BlockEditorBoundaryProps {
    /** Parsed block tree for the active entity. */
    blocks: readonly unknown[];
    onChange: (blocks: readonly unknown[]) => void;
    onInput: (blocks: readonly unknown[]) => void;
    /**
     * API base for the "Convert to pattern" control. Omitted (or empty)
     * when the section doesn't surface the control.
     */
    apiBase?: string;
    /**
     * Test-only override for the active theme's compiled CSS. In
     * production the boundary fetches this through
     * {@see useThemeGlobalStylesCss} keyed on `apiBase`; tests pass an
     * explicit string to avoid hitting the network. When `undefined`
     * the hook drives the value (Keystone #47).
     */
    themeGlobalStylesCss?: string;
    /**
     * Optional canvas-navigation callback. Some block-library actions
     * (notably `core/navigation`'s Create Overlay flow) ask the editor
     * to focus a freshly-created entity record by calling this hook
     * with `{ postId, postType }`. The boundary forwards the callback
     * into `BlockEditorProvider`'s `settings.onNavigateToEntityRecord`
     * slot — without it, those flows succeed server-side but the
     * editor shows no feedback. When the prop is omitted, the
     * boundary installs a sensible default that swaps the SPA URL to
     * `{routeBase}/template-parts/{slug-or-id}` (Keystone #55).
     */
    onNavigateToEntityRecord?: (target: NavigateToEntityRecordTarget) => void;
    /**
     * Canvas and inspector slots. Both must be rendered as children so
     * they share this boundary's `core/block-editor` registry — even
     * when a section portals them into separate DOM nodes.
     */
    children: ReactNode;
}

/**
 * Shape `core/navigation`'s Create Overlay action (and any similar
 * block-library flow) passes to {@see BlockEditorBoundaryProps.onNavigateToEntityRecord}.
 *
 * `postId` is a composite `{theme}//{slug}` id minted by the block
 * library — that's the same shape our `getEntityRecord` resolver
 * already understands (see `core-data-shim` composite-id handling).
 */
export interface NavigateToEntityRecordTarget {
    postId: number | string;
    postType: string;
    viewport?: string;
}

export function BlockEditorBoundary(props: BlockEditorBoundaryProps): JSX.Element {
    const {
        blocks,
        onChange,
        onInput,
        apiBase,
        themeGlobalStylesCss,
        onNavigateToEntityRecord,
        children,
    } = props;

    // Default canvas-navigation handler — swaps the SPA URL when a
    // block-library action asks us to focus a freshly-created entity.
    // The host can pass an explicit `onNavigateToEntityRecord` to
    // intercept (e.g., a router-aware push); when omitted, the boundary
    // installs this fallback so flows like Create Overlay don't no-op
    // (Keystone #55).
    const navigateToEntityRecord = useMemo(() => {
        if (onNavigateToEntityRecord !== undefined) {
            return onNavigateToEntityRecord;
        }

        return (target: NavigateToEntityRecordTarget): void => {
            if (typeof window === 'undefined') {
                return;
            }

            const postType = target.postType ?? '';
            const segment =
                postType === 'wp_template_part'
                    ? 'template-parts'
                    : postType === 'wp_template'
                      ? 'templates'
                      : '';

            if (segment === '') {
                return;
            }

            // `postId` arrives from Gutenberg as a composite
            // `{theme}//{slug}` string. Hosts route by the entity's
            // numeric DB id (e.g., `/template-parts/9`), so look the
            // record up in our entity store and prefer its `id` field
            // when one is set (`TemplatePartAdapter` ships
            // `id = wpId` for DB-backed rows). Falls back to the raw
            // composite when no record is cached — keeps the call
            // testable and gives standalone installs a deterministic
            // URL even without the entity registry warm.
            const navigationId = resolveNavigationId(target.postId, postType);

            // Resolve the route base from the editor mount's
            // `data-route-base` attribute. The Keystone CMS host
            // sets `data-ap-site-editor` on its mount; standalone
            // installs use `data-ap-visual-editor`. Match either so
            // we don't have to know which host we're embedded in.
            const mount = document.querySelector(
                '[data-ap-site-editor][data-route-base], [data-ap-visual-editor][data-route-base]'
            );
            const routeBase =
                mount?.getAttribute('data-route-base') ??
                '/visual-editor/site';

            const targetPath = `${routeBase}/${segment}/${navigationId}`;

            // Client-side navigation: the SPA's own routing hook
            // (`useSiteEditorRouting`) listens for `popstate` and
            // re-parses `window.location.pathname` on every event.
            // Push the new URL onto history and synthesize a popstate
            // so the listener fires. A full `window.location.assign`
            // also works but boots the SPA fresh, and the user reported
            // hydration glitches (canvas briefly stuck on the previous
            // section's "select a record" placeholder) when navigating
            // across sections programmatically. PushState avoids the
            // remount entirely (Keystone #55).
            if (window.location.pathname === targetPath) {
                return;
            }

            window.history.pushState({ segment, navigationId }, '', targetPath);
            window.dispatchEvent(new PopStateEvent('popstate'));
        };
    }, [onNavigateToEntityRecord]);

    // Tests can short-circuit the network by passing a string directly;
    // production drives the value through the hook keyed on `apiBase`.
    const fetchedCss = useThemeGlobalStylesCss(apiBase);
    const themeCss = themeGlobalStylesCss !== undefined ? themeGlobalStylesCss : fetchedCss;

    // Pull the active theme's `settings` (palette, font-sizes, etc.)
    // so the picker UIs source their swatches from the same slugs the
    // server-side emitter binds via `.has-{slug}-*` rules. Without
    // this the editor used a hard-coded default palette while the
    // emitter bound the theme palette — picker choices didn't
    // visually apply because the two palettes don't share slugs
    // (Keystone #53).
    const themeBase = useThemeGlobalStylesSettings(apiBase);

    // Append the theme's compiled global-styles CSS to the editor's
    // `styles` array so Gutenberg cascades it into the canvas, and
    // override the palette / font-sizes in `__experimentalFeatures`
    // when the theme defines them. Memoized so identity-stable
    // `editorSettings` doesn't bust the provider's effects on every
    // parent re-render. When no theme data is available we hand the
    // provider the original `editorSettings` object — no allocation,
    // no diff.
    const settings = useMemo(() => {
        const themeSettings = themeBase?.settings ?? {};
        const themePalette = extractThemePalette(themeSettings);
        const themeFontSizes = extractThemeFontSizes(themeSettings);
        const themeGradients = extractThemeGradients(themeSettings);

        const needsStyles = themeCss !== undefined && themeCss !== '';
        const needsPalette = themePalette !== null;
        const needsFontSizes = themeFontSizes !== null;
        const needsGradients = themeGradients !== null;

        if (!needsStyles && !needsPalette && !needsFontSizes && !needsGradients) {
            return {
                ...editorSettings,
                onNavigateToEntityRecord: navigateToEntityRecord,
            };
        }

        const nextStyles = needsStyles
            ? [...editorSettings.styles, { css: themeCss as string }]
            : editorSettings.styles;

        const baseFeatures = editorSettings.__experimentalFeatures ?? {};
        const baseColor = (baseFeatures as { color?: Record<string, unknown> }).color ?? {};
        const baseTypography =
            (baseFeatures as { typography?: Record<string, unknown> }).typography ?? {};

        return {
            ...editorSettings,
            styles: nextStyles,
            onNavigateToEntityRecord: navigateToEntityRecord,
            // Mirror onto the legacy top-level keys too so older
            // blocks that still read `settings.colors` / `fontSizes`
            // pick up the theme's slugs.
            ...(needsPalette ? { colors: themePalette } : {}),
            ...(needsFontSizes ? { fontSizes: themeFontSizes } : {}),
            __experimentalFeatures: {
                ...baseFeatures,
                color: {
                    ...baseColor,
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
                typography: needsFontSizes
                    ? {
                          ...baseTypography,
                          fontSizes: {
                              ...(((baseTypography as { fontSizes?: Record<string, unknown> })
                                  .fontSizes) ?? {}),
                              theme: themeFontSizes,
                          },
                      }
                    : baseTypography,
            },
        };
    }, [themeCss, themeBase, navigateToEntityRecord]);

    return (
        <SlotFillProvider>
            <BlockEditorProvider
                value={blocks}
                settings={settings}
                onChange={onChange}
                onInput={onInput}
            >
                {children}
                <Popover.Slot />
                {apiBase !== undefined && apiBase !== '' ? (
                    <ConvertToPatternControl apiBase={apiBase} />
                ) : null}
            </BlockEditorProvider>
        </SlotFillProvider>
    );
}

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

interface GradientEntry {
    slug: string;
    name: string;
    gradient: string;
}

/**
 * Resolve the URL-safe id for a navigation target. Gutenberg's
 * Create Overlay (and similar) hands the composite `{theme}//{slug}`
 * id its block-library uses internally, but hosts route by the
 * entity's numeric DB id (`/template-parts/9`). Look the record up
 * in `@wordpress/data`'s `'core'` store (our shim) and prefer its
 * `id` field — for DB-backed rows {@see TemplatePartAdapter} sets
 * `id = wpId`, so the lookup returns the numeric primary key.
 *
 * Falls back to the raw composite when no record is cached — keeps
 * the helper testable and gives standalone installs a deterministic
 * URL even before the entity registry warms up.
 *
 * @since 1.1.0
 */
function resolveNavigationId(
    postId: number | string,
    postType: string,
): string {
    const composite = typeof postId === 'number' ? String(postId) : postId;

    try {
        const store = wpSelect( 'core' ) as
            | {
                  getEntityRecord?: (
                      kind: string,
                      name: string,
                      id: number | string,
                  ) => { id?: number | string } | null;
              }
            | undefined;

        const record = store?.getEntityRecord?.(
            'postType',
            postType,
            composite,
        );
        const resolved = record?.id;

        if (typeof resolved === 'number' || typeof resolved === 'string') {
            return encodeURIComponent(String(resolved));
        }
    } catch {
        // Defensive — `@wordpress/data` might not have our store
        // registered in some test contexts. Fall back to the raw
        // composite below so navigation still produces a URL.
    }

    return encodeURIComponent(composite);
}

/**
 * Pull `settings.color.palette` out of the `/global-styles/base`
 * payload as a normalized `{ slug, name, color }` list. Returns
 * `null` (not an empty array) when the theme didn't define one so
 * the boundary can keep `editorSettings`'s default palette as a
 * fallback rather than blanking the picker.
 */
function extractThemePalette(
    settings: Record<string, unknown>,
): readonly PaletteEntry[] | null {
    const palette = (settings.color as { palette?: unknown })?.palette;

    if (!Array.isArray(palette) || palette.length === 0) {
        return null;
    }

    const out: PaletteEntry[] = [];

    for (const entry of palette) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const color = (entry as { color?: unknown }).color;

        if (typeof slug !== 'string' || slug === '' || typeof color !== 'string') {
            continue;
        }

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            color,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

function extractThemeFontSizes(
    settings: Record<string, unknown>,
): readonly FontSizeEntry[] | null {
    const sizes = (settings.typography as { fontSizes?: unknown })?.fontSizes;

    if (!Array.isArray(sizes) || sizes.length === 0) {
        return null;
    }

    const out: FontSizeEntry[] = [];

    for (const entry of sizes) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const size = (entry as { size?: unknown }).size;

        if (typeof slug !== 'string' || slug === '' || typeof size !== 'string') {
            continue;
        }

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            size,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}

function extractThemeGradients(
    settings: Record<string, unknown>,
): readonly GradientEntry[] | null {
    const gradients = (settings.color as { gradients?: unknown })?.gradients;

    if (!Array.isArray(gradients) || gradients.length === 0) {
        return null;
    }

    const out: GradientEntry[] = [];

    for (const entry of gradients) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const slug = (entry as { slug?: unknown }).slug;
        const gradient = (entry as { gradient?: unknown }).gradient;

        if (typeof slug !== 'string' || slug === '' || typeof gradient !== 'string') {
            continue;
        }

        const name = (entry as { name?: unknown }).name;

        out.push({
            slug,
            gradient,
            name: typeof name === 'string' ? name : slug,
        });
    }

    return out.length === 0 ? null : out;
}
