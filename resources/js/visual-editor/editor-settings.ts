/**
 * Shared block-editor settings — #436.
 *
 * The post editor (`editor/editor-app.tsx`) and the site editor
 * (`site-editor/block-editor-boundary.tsx`) both feed a `settings`
 * object into `BlockEditorProvider`. That object decides which
 * inspector panels light up: the Color panel needs a palette + the
 * `color` features, the Typography font-size / font-family pickers
 * need their preset lists, and so on.
 *
 * The site editor used to boot with a minimal `{ alignWide,
 * hasFixedToolbar }` placeholder, so its inspector showed far fewer
 * panels than the post editor. That gap was invisible until #436 fixed
 * the inspector's provider scope — once block selection reached the
 * inspector, the under-configuration became visible. Both editors now
 * share this one object so their inspectors match.
 *
 * `__experimentalFeatures` is intentionally narrow: `link`,
 * `defaultPalette`, `defaultGradients`, and `defaultDuotone` assume
 * WordPress-core preset data this package doesn't ship, and turning
 * them on without that data triggers an infinite render loop inside
 * the color picker during drag. Only enable a feature once the data
 * backing it is also wired. Theme.json integration (B3) will later
 * replace these seeded defaults with the active theme's real presets.
 */

import { __ } from '@wordpress/i18n';

import { mediaUploadSetting } from './media-bridge';
import { TEXT_DOMAIN } from './vendor/i18n';

/**
 * DaisyUI-aligned color palette. Hosts can override by publishing
 * their own settings once theme.json integration (B3) lands; for V1
 * this seeds a coherent default so new installs aren't staring at an
 * empty color picker.
 *
 * Preset labels are wrapped with `__()` so the pot-extraction command
 * picks them up; when translations aren't loaded `__()` returns the
 * English source unchanged.
 */
export const DEFAULT_PALETTE = [
    { name: __('Base content', TEXT_DOMAIN), slug: 'base-content', color: '#1f2937' },
    { name: __('Base muted', TEXT_DOMAIN), slug: 'base-muted', color: '#6b7280' },
    { name: __('Primary', TEXT_DOMAIN), slug: 'primary', color: '#2563eb' },
    { name: __('Secondary', TEXT_DOMAIN), slug: 'secondary', color: '#64748b' },
    { name: __('Accent', TEXT_DOMAIN), slug: 'accent', color: '#9333ea' },
    { name: __('Success', TEXT_DOMAIN), slug: 'success', color: '#16a34a' },
    { name: __('Warning', TEXT_DOMAIN), slug: 'warning', color: '#d97706' },
    { name: __('Error', TEXT_DOMAIN), slug: 'error', color: '#dc2626' },
];

export const DEFAULT_FONT_SIZES = [
    { name: __('Small', TEXT_DOMAIN), slug: 'small', size: '13px' },
    { name: __('Regular', TEXT_DOMAIN), slug: 'regular', size: '16px' },
    { name: __('Medium', TEXT_DOMAIN), slug: 'medium', size: '20px' },
    { name: __('Large', TEXT_DOMAIN), slug: 'large', size: '28px' },
    { name: __('Huge', TEXT_DOMAIN), slug: 'huge', size: '36px' },
];

/**
 * Default spacing scale exposed to the spacing panel when a theme hasn't
 * shipped its own `settings.spacing.spacingSizes`. Slugs run `20 → 70`,
 * matching WordPress core's numeric-stepped slug convention so saved
 * markup stays portable when a theme overrides with its own scale.
 */
export const DEFAULT_SPACING_SIZES = [
    { slug: '20', name: __('Tight', TEXT_DOMAIN), size: '0.5rem' },
    { slug: '30', name: __('Small', TEXT_DOMAIN), size: '1rem' },
    { slug: '40', name: __('Medium', TEXT_DOMAIN), size: '1.5rem' },
    { slug: '50', name: __('Large', TEXT_DOMAIN), size: '3rem' },
    { slug: '60', name: __('X-Large', TEXT_DOMAIN), size: '5rem' },
    { slug: '70', name: __('Section', TEXT_DOMAIN), size: '7rem' },
];

export const DEFAULT_SPACING_UNITS = ['px', 'em', 'rem', '%', 'vh', 'vw'];

export const DEFAULT_FONT_FAMILIES = [
    {
        name: __('System', TEXT_DOMAIN),
        slug: 'system',
        fontFamily:
            'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    },
    {
        name: __('Serif', TEXT_DOMAIN),
        slug: 'serif',
        fontFamily: 'Georgia, Cambria, "Times New Roman", Times, serif',
    },
    {
        name: __('Monospaced', TEXT_DOMAIN),
        slug: 'mono',
        fontFamily:
            'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace',
    },
];

/**
 * Default canvas stylesheet. Injected via `settings.styles` so blocks
 * have a sensible typographic baseline before a theme.json kicks in.
 * Themes override these by emitting `styles.typography` /
 * `styles.elements.*` blocks in theme.json — the theme's stylesheet
 * ships later in the `styles` array and wins on cascade.
 *
 * Scoped under `.editor-styles-wrapper` because Gutenberg auto-prepends
 * that selector to every style entry it isn't already scoped to,
 * keeping these rules out of the editor chrome.
 */
export const DEFAULT_CANVAS_STYLES = `
.editor-styles-wrapper {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: #1f2937;
}

.editor-styles-wrapper p {
    margin: 0 0 1em;
    font-size: 1rem;
    line-height: 1.6;
}

.editor-styles-wrapper h1,
.editor-styles-wrapper h2,
.editor-styles-wrapper h3,
.editor-styles-wrapper h4,
.editor-styles-wrapper h5,
.editor-styles-wrapper h6 {
    font-family: inherit;
    font-weight: 700;
    line-height: 1.2;
    margin: 1.4em 0 0.6em;
    color: #111827;
}

.editor-styles-wrapper h1 { font-size: 2.5rem; }
.editor-styles-wrapper h2 { font-size: 2rem; }
.editor-styles-wrapper h3 { font-size: 1.5rem; }
.editor-styles-wrapper h4 { font-size: 1.25rem; }
.editor-styles-wrapper h5 { font-size: 1.125rem; }
.editor-styles-wrapper h6 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; }

.editor-styles-wrapper a {
    color: #2563eb;
    text-decoration: underline;
}

.editor-styles-wrapper blockquote {
    border-left: 4px solid #e5e7eb;
    margin: 1.5em 0;
    padding: 0 0 0 1em;
    font-style: italic;
    color: #4b5563;
}

.editor-styles-wrapper ul,
.editor-styles-wrapper ol {
    margin: 0 0 1em;
    padding-left: 1.5em;
}

.editor-styles-wrapper code {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.9em;
    background: #f3f4f6;
    padding: 0.1em 0.35em;
    border-radius: 4px;
}

.editor-styles-wrapper pre {
    background: #f3f4f6;
    padding: 1em;
    border-radius: 6px;
    overflow-x: auto;
    margin: 0 0 1em;
}

.editor-styles-wrapper hr {
    border: 0;
    border-top: 1px solid #e5e7eb;
    margin: 2em 0;
}
`;

/**
 * Per-block alignment overrides for the root canvas. Shipped via
 * {@link DEFAULT_CANVAS_STYLES} since both editors need them — the
 * toolbar's wide/full buttons set `data-align="wide" | "full"` on the
 * block; these rules make those attributes visually take effect by
 * resizing each direct child of the root layout (Keystone #47).
 *
 * The previous model clamped the root layout itself to 720px and then
 * tried to override with `.wp-block[data-align=...] { max-width }` —
 * which doesn't break out because the parent layout is the cap. This
 * model flips it: the root layout is full-bleed, and direct children
 * are sized individually by alignment.
 *
 * Scoped to `> .wp-block` so nested blocks (inside `core/group`,
 * `core/columns`, etc.) aren't affected — those nested layouts have
 * their own constrained / flex / grid rules.
 */
export const ALIGNMENT_OVERRIDE_STYLES = `
.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block.alignwide {
    max-width: 1080px;
    margin-left: auto;
    margin-right: auto;
}

.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block.alignfull {
    max-width: none;
    margin-left: 0;
    margin-right: 0;
}
`;

/**
 * Post-editor-only canvas framing (Keystone #47). Lifted out of
 * {@link DEFAULT_CANVAS_STYLES} so the site editor's canvas — which
 * edits templates and template parts that span the full viewport on
 * the front-end — doesn't inherit a 720px content column it has no
 * business showing. Post content edits sit inside the front-end's
 * constrained column, so the post editor keeps the framing.
 *
 * Targets each direct child of the root layout rather than the root
 * itself so blocks marked `data-align="wide" | "full"` (see
 * {@link ALIGNMENT_OVERRIDE_STYLES}) can break out — clamping the
 * root container would cap every child regardless of alignment.
 *
 * The `is-root-container` scope keeps nested layouts (`<li>` inside
 * `core/list`, columns inside `core/columns`, every inner container
 * of `core/group`) out of the rule so each child of a nested layout
 * doesn't inherit 720px / 48px padding too.
 */
export const POST_EDITOR_FRAMING_STYLES = `
.editor-styles-wrapper .block-editor-block-list__layout.is-root-container {
    padding: 48px 24px 96px;
}

.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block:not(.alignwide):not(.alignfull) {
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
}
`;

/**
 * Layout descriptor passed to the root `<BlockList layout={...}>` so
 * Gutenberg's `BlockLayoutContext` resolves to a `constrained` layout
 * with explicit `contentSize` / `wideSize`. Without it the root
 * `BlockList` falls back to the package-internal `{type:"default"}`
 * default, whose `getAlignments()` returns an empty list — the actual
 * reason the wide / full alignment buttons never appeared on the
 * block toolbar (Keystone #47).
 *
 * Hardcoded for now; a follow-up will sync `contentSize` / `wideSize`
 * from the active theme's `theme.json` `settings.layout` via the
 * `/global-styles/base` endpoint.
 */
export const ROOT_CANVAS_LAYOUT = {
    type: 'constrained',
    contentSize: '720px',
    wideSize: '1080px',
} as const;

/**
 * Block-editor settings seeded with the block-support features needed
 * to light up the inspector panels (Color, Typography, Dimensions,
 * Border, Layout) and the toolbar text-align control. Fed into
 * `BlockEditorProvider` by both the post editor and the site editor.
 */
export const editorSettings = {
    mediaUpload: mediaUploadSetting,
    alignWide: true,
    /*
     * Top-level `layout` mirror of `__experimentalFeatures.layout`.
     * Mirrored as a value via `ROOT_CANVAS_LAYOUT` below — the canvas
     * components pass that constant as the `layout` prop to the root
     * `<BlockList>` so Gutenberg's layout context resolves to
     * `constrained` instead of the `{type:"default"}` fallback, which
     * was the actual reason wide/full alignment buttons never showed
     * on the block toolbar (Keystone #47).
     * Gutenberg's `useAvailableAlignments` reads from this top-level
     * setting when deciding whether to show wide/full in the block
     * toolbar — without it the buttons are hidden even though
     * `alignWide: true` and the block declares `supports.align`
     * (Keystone #47). The explicit `type: "constrained"` is
     * required: the constrained-layout `getAlignments` is what
     * unshifts `wide` / `full` into the available list (it reads
     * `contentSize` / `wideSize` off this same object). Without
     * the type, Gutenberg falls back to the default handler which
     * surfaces only left/center/right. The
     * `__experimentalFeatures.layout` path stays around because
     * other Gutenberg surfaces (the layout panel in the inspector)
     * read from there.
     */
    layout: {
        type: 'constrained',
        contentSize: '720px',
        wideSize: '1080px',
    },
    /*
     * Required alongside `layout` so `useAvailableAlignments` takes
     * the "supports layout" branch — which invokes the constrained
     * layout's own `getAlignments(layout, blockBasedTheme)` and
     * returns wide+full. Without it the function falls into the
     * legacy branch that only checks the canonical alignment list
     * and never actually exposes wide/full on `core/group`.
     */
    supportsLayout: true,
    /*
     * Default canvas stylesheet (`{ css }`) gives blocks a typographic
     * baseline so headings/paragraphs/links visually differentiate
     * without a theme.json. Themes ship their own `{ css, baseURL }`
     * entries via the theme.json bridge (planned) — they're appended
     * to this array and win on cascade.
     */
    styles: [{ css: DEFAULT_CANVAS_STYLES }],
    // Top-level legacy keys are still read by some core blocks that
    // haven't migrated to `__experimentalFeatures`.
    colors: DEFAULT_PALETTE,
    fontSizes: DEFAULT_FONT_SIZES,
    // `__experimentalFeatures` lights up the inspector panels
    // (Color, Typography, Dimensions, Border) plus the toolbar
    // text-alignment control.
    //
    // History: this block previously excluded `spacing`, `border`,
    // `dimensions`, `defaultPalette`, `defaultGradients`, and
    // `defaultDuotone` to avoid an upstream Gutenberg drag-loop bug
    // that fired when color/spacing controls escaped the editor frame.
    // The Phase A iframe migration scopes those events to the canvas
    // iframe, so `spacing`/`border`/`dimensions` are now re-enabled
    // here. Default palette/gradient/duotone keys are still left off —
    // they pull in upstream preset data we don't ship, and the
    // palette/font-size data is already surfaced via the explicit
    // `palette` and `fontSizes` entries below.
    __experimentalFeatures: {
        layout: {
            contentSize: '720px',
            wideSize: '1080px',
        },
        color: {
            custom: true,
            customGradient: false,
            text: true,
            background: true,
            // `link: true` lights up the Link color row in the inspector
            // (used by `core/navigation`, `core/post-content`, etc).
            // The previously-warned "drag loop" came from turning on
            // upstream-core flags (`defaultPalette`, `defaultGradients`,
            // `defaultDuotone`) without the preset data backing them.
            // `link` is data-backed by the palette below — no loop
            // (Keystone #53).
            link: true,
            // Expose the palette through the modern features path so
            // the nav block's color picker (and any block that reads
            // `__experimentalFeatures.color.palette.theme` first)
            // surfaces swatches instead of empty rows. Mirror the
            // legacy top-level `colors:` array so both code paths
            // converge on the same data.
            // `__experimentalFeatures.color.palette.{theme,custom,default}`
            // each take an ARRAY of `{slug, name, color}` entries —
            // NOT a boolean. Gutenberg's `with-colors` HOC spreads
            // all three together (`[...theme, ...custom, ...default]`),
            // so a `custom: true` here crashes with "spread requires
            // iterable" in `with-colors.mjs`. Leave `custom` as an
            // empty array; user-defined custom colors get added via
            // the picker UI at runtime, not seeded here.
            palette: {
                theme: DEFAULT_PALETTE,
                custom: [],
            },
        },
        typography: {
            customFontSize: true,
            fontStyle: true,
            fontWeight: true,
            letterSpacing: true,
            lineHeight: true,
            textAlign: true,
            textDecoration: true,
            textTransform: true,
            // Mirror the palette/spacingSizes pattern below: ship presets
            // under the `theme` origin (not `custom`) so that when the
            // themed-editor-settings hook overrides with a host theme's
            // `theme:` array, the two don't stack. Gutenberg's
            // `TypographyPanel.getMergedFontSizes` concatenates
            // `[...custom, ...theme, ...default]` — keeping defaults under
            // `custom` while the hook adds `theme:` produced duplicate
            // React keys (e.g. `small`, `large`) in `FontSizePickerSelect`
            // when slug sets overlapped (#547).
            fontSizes: { theme: DEFAULT_FONT_SIZES, custom: [] },
            fontFamilies: { theme: DEFAULT_FONT_FAMILIES, custom: [] },
        },
        spacing: {
            // Padding, margin, blockGap → the Dimensions panel on
            // every block that declares `supports.spacing`. The
            // spacing scale below is the default theme.json mirror;
            // host themes override via `settings.spacing.spacingSizes`.
            padding: true,
            margin: true,
            blockGap: true,
            customSpacingSize: true,
            units: DEFAULT_SPACING_UNITS,
            spacingScale: { steps: 0 },
            spacingSizes: { theme: DEFAULT_SPACING_SIZES, custom: [] },
        },
        border: {
            // Border controls (color, radius, style, width) → the
            // Border panel on every block that declares
            // `supports.__experimentalBorder`.
            color: true,
            radius: true,
            style: true,
            width: true,
        },
        dimensions: {
            // Min-height + aspect-ratio → the Dimensions panel on
            // blocks like core/cover, core/post-featured-image, and
            // our `artisanpack/cover` / `artisanpack/group` forks.
            minHeight: true,
            aspectRatio: true,
        },
    },
};
