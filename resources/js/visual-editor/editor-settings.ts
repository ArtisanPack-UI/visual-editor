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

import { canUseUnfilteredHtml } from './editor/unfiltered-html-access';
import { __ } from '@wordpress/i18n';

import { getNavigationOverlayPattern } from './editor/navigation-overlay-pattern';
import { fetchLinkSuggestions } from './link-suggestions';
import { mediaUploadSetting } from './media-bridge';
import { getHostPresets, mergePresetList } from './preset-registry';
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
 * Effective preset arrays after host `config('artisanpack.visual-editor.presets')`
 * additions/overrides are merged into the package defaults (#773). Read at
 * module-load time from the `data-presets` mount attribute; when no host
 * presets are configured these are byte-equal to the `DEFAULT_*` arrays.
 *
 * When a theme ships `theme.json` presets, `useThemedEditorSettings`
 * layers the same host presets on top of the theme layer too — so brand
 * entries reach the picker regardless of whether the host also runs a
 * theme.
 */
const HOST_PRESETS = getHostPresets();
export const EFFECTIVE_PALETTE = mergePresetList(DEFAULT_PALETTE, HOST_PRESETS.palette);
export const EFFECTIVE_FONT_SIZES = mergePresetList(DEFAULT_FONT_SIZES, HOST_PRESETS.fontSizes);
export const EFFECTIVE_FONT_FAMILIES = mergePresetList(
    DEFAULT_FONT_FAMILIES,
    HOST_PRESETS.fontFamilies,
);
export const EFFECTIVE_SPACING_SIZES = mergePresetList(
    DEFAULT_SPACING_SIZES,
    HOST_PRESETS.spacingSizes,
);

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
 *
 * Element margins live in {@link CANVAS_LAYOUT_STYLES}, not here: at this
 * specificity they beat the layout baseline and Gutenberg's per-container
 * Block spacing rules (#1040).
 */
export const DEFAULT_CANVAS_STYLES = `
.editor-styles-wrapper {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: #1f2937;
}

.editor-styles-wrapper p {
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
    /*
     * #695 — this baseline (0,1,1) out-specifies a theme's bare
     * \`h1\`..\`h6\` rules, so a hardcoded hex here pins headings to a
     * light-mode color no dark theme can shift: on a dark canvas they
     * render near-invisible against the ground. Chain through the
     * canvas tokens instead — the theme's heading color when it
     * declares one, otherwise the canvas foreground, otherwise the
     * original light default. See \`editor/canvas-color-tokens.ts\`.
     */
    color: var(--ap-editor-canvas-heading-fg, var(--ap-editor-canvas-fg, #111827));
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
    padding: 0 0 0 1em;
    font-style: italic;
    color: #4b5563;
}

.editor-styles-wrapper ul,
.editor-styles-wrapper ol {
    padding-left: 1.5em;
}

/*
 * #1051 — zero specificity, so a theme's \`styles.blocks\` rules (0,1,0)
 * reach Code and Preformatted in the canvas as they do on the front end.
 * The tints derive from \`currentColor\` so the text stays readable on any
 * theme. Verse is poetry set in a \`pre\`, so it gets no panel, and code
 * inside a panel gets no second chip.
 */
:where(.editor-styles-wrapper code) {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.9em;
    background: color-mix(in srgb, currentColor 10%, transparent);
    padding: 0.1em 0.35em;
    border-radius: 4px;
}

:where(.editor-styles-wrapper pre:not(.wp-block-verse)) {
    background: color-mix(in srgb, currentColor 8%, transparent);
    padding: 1em;
    border-radius: 6px;
    overflow-x: auto;
}

:where(.editor-styles-wrapper pre:not(.wp-block-verse) code) {
    font-size: inherit;
    background: none;
    padding: 0;
    border-radius: 0;
}

.editor-styles-wrapper hr {
    border: 0;
    border-top: 1px solid #e5e7eb;
}

/*
 * #1102 — the empty area below the root block list. Clicking it appends
 * (or focuses) a trailing empty paragraph, so it gets enough height to be
 * an easy target and a text cursor while it can take the default block.
 * While it can't (a template lock, for example) clicks pass through to the
 * canvas, so clicking below the content still clears the block selection.
 * The composed view shrinks it so the footer chrome stays near the
 * content.
 */
.editor-styles-wrapper .ap-canvas-end-click-target {
    min-height: max(96px, 40vh);
}

.editor-styles-wrapper .ap-canvas-end-click-target--compact {
    min-height: 96px;
}

.editor-styles-wrapper .ap-canvas-end-click-target.is-enabled {
    cursor: text;
}

.editor-styles-wrapper .ap-canvas-end-click-target:not(.is-enabled) {
    pointer-events: none;
}
`;

/**
 * Default element margins, for content outside a layout container (root
 * blocks, Accordion bodies).
 *
 * Zero specificity, and printed ahead of {@link LAYOUT_BASELINE_STYLES},
 * so a layout container's rules decide its children's margins. A theme's
 * bare `p` or `h2` rule also wins, as it does on the front end (#1040).
 */
const DEFAULT_ELEMENT_MARGIN_STYLES = `
:where(.editor-styles-wrapper p) { margin: 0 0 1em; }
:where(.editor-styles-wrapper :is(h1, h2, h3, h4, h5, h6)) { margin: 1.4em 0 0.6em; }
:where(.editor-styles-wrapper blockquote) { margin: 1.5em 0; }
:where(.editor-styles-wrapper :is(ul, ol)) { margin: 0 0 1em; }
:where(.editor-styles-wrapper pre) { margin: 0 0 1em; }
:where(.editor-styles-wrapper hr) { margin: 2em 0; }
`;

/**
 * Layout baseline rules: flow, constrained, flex and grid.
 *
 * Upstream WP emits these rules at PAGE RENDER time via
 * `WP_Block_Supports_Layout` (PHP front-end) and `useLayoutStyles`
 * (editor — but only when the block has a non-default layout attribute
 * like `justifyContent`; the `display: flex` rule itself is NOT
 * emitted by `useLayoutStyles.getLayoutStyle` in
 * `@wordpress/block-editor`). Neither the bundled
 * `@wordpress/block-library/style.css` nor `editor.css` define
 * `.is-layout-flex { display: flex }` as a static rule.
 *
 * Result without them: blocks with `supports.layout.default.type =
 * "flex"` in block.json (gallery, buttons, cover, group's row/stack
 * variations) render with `is-layout-flex` on the wrapper but no flex
 * display rule, so they fall back to block flow and inner blocks stack
 * vertically.
 *
 * Ship the baseline rules statically here so the canvas matches the
 * front-end (which gets the same rules via `<x-ve-blocks-styles>`).
 * Generic `.is-layout-flex` / `.is-layout-grid` selectors mean
 * per-block compound classes (`wp-block-gallery-is-layout-flex`, …)
 * inherit without enumerating block names.
 *
 * Gutenberg writes a per-container rule only when a block sets its own
 * Block spacing, so the zero-specificity margin (#1040) and `gap` (#883)
 * rules are what space a container that inherits the theme's
 * `blockGap`. Without them a Group, Row or Grid with no Block spacing
 * renders flush in the canvas but not on the site.
 *
 * The flex defaults (`flex-wrap`, `align-items`) are zero specificity
 * (#927), so the Flex panel's `ap-flex-nowrap` / `ap-items-*` utilities
 * win wherever they load.
 *
 * These rules are copied from the Blade, React and Vue baselines rather
 * than generated from them, because the Blade rules live in a template.
 * `canvas-styles.test.ts` fails if they drift. The one deliberate change
 * is the root-gap selector: `:where(.editor-styles-wrapper)` replaces
 * `:where(body)`, because the theme's `blockGap` lands on that wrapper
 * (see `scopeGlobalStylesCss`), which isn't `body` in the site editor.
 */
export const LAYOUT_BASELINE_STYLES = `
:where(.is-layout-flow) > :first-child { margin-block-start: 0; }
:where(.is-layout-flow) > :last-child { margin-block-end: 0; }
:where(.is-layout-flow) > * { margin-block-start: 0; margin-block-end: 0; }
:where(.is-layout-flow) > * + * { margin-block-start: var(--wp--style--block-gap, 24px); margin-block-end: 0; }
:where(.is-layout-constrained) > :first-child { margin-block-start: 0; }
:where(.is-layout-constrained) > :last-child { margin-block-end: 0; }
:where(.is-layout-constrained) > * { margin-block-start: 0; margin-block-end: 0; }
:where(.is-layout-constrained) > * + * { margin-block-start: var(--wp--style--block-gap, 24px); margin-block-end: 0; }
.is-layout-flex { display: flex; }
:where(.is-layout-flex) { flex-wrap: wrap; align-items: center; }
.is-layout-flex > :is(*, div) { margin: 0; }
.is-layout-grid { display: grid; }
.is-layout-grid > :is(*, div) { margin: 0; }
:where(.is-layout-flex) { gap: var(--wp--style--block-gap, 0.5em); }
:where(.is-layout-grid) { gap: var(--wp--style--block-gap, 0.5em); }
:where(.editor-styles-wrapper) { --ap-root-block-gap: var(--wp--style--block-gap); }
:where(.is-layout-flow, .is-layout-constrained, .is-layout-flex, .is-layout-grid) { --wp--style--block-gap: var(--ap-root-block-gap); }
`;

/**
 * Element margins and the layout baseline, in the order they must print.
 * Every canvas and preview needs this alongside
 * {@link DEFAULT_CANVAS_STYLES}.
 */
export const CANVAS_LAYOUT_STYLES = DEFAULT_ELEMENT_MARGIN_STYLES + LAYOUT_BASELINE_STYLES;

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

/*
 * #819 — floated alignments. With \`supportsLayout\` on, Gutenberg puts
 * \`alignleft\` / \`alignright\` straight on the block and relies on the
 * core layout definitions to float it, which the canvas never receives.
 * The inline-start offset lines the float up with the content column,
 * matching the Blade renderer's \`floatRules()\`.
 */
.editor-styles-wrapper .is-layout-constrained > .alignleft {
    float: left;
    margin-inline-start: max(0px, calc((100% - var(--wp--style--global--content-size, 720px)) / 2));
    margin-inline-end: 2em;
}

.editor-styles-wrapper .is-layout-constrained > .alignright {
    float: right;
    margin-inline-start: 2em;
    margin-inline-end: max(0px, calc((100% - var(--wp--style--global--content-size, 720px)) / 2));
}

.editor-styles-wrapper .is-layout-flow > .alignleft {
    float: left;
    margin-inline-start: 0;
    margin-inline-end: 2em;
}

.editor-styles-wrapper .is-layout-flow > .alignright {
    float: right;
    margin-inline-start: 2em;
    margin-inline-end: 0;
}

.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block.alignleft {
    float: left;
    margin-inline-start: max(0px, calc((100% - 720px) / 2));
    margin-inline-end: 2em;
}

.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block.alignright {
    float: right;
    margin-inline-start: 2em;
    margin-inline-end: max(0px, calc((100% - 720px) / 2));
}

/*
 * Floats stack at the Mobile breakpoint, as on the front end. The canvas
 * is an iframe sized to the preview width, so this matches the Mobile
 * preview (#820).
 */
@media (max-width: 767px) {
    .editor-styles-wrapper .is-layout-constrained > :is(.alignleft, .alignright),
    .editor-styles-wrapper .is-layout-flow > :is(.alignleft, .alignright),
    .editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block:is(.alignleft, .alignright) {
        float: none;
        margin-inline: auto;
    }
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
 *
 * The gap rule stands in for the constrained Post Content block that
 * wraps these blocks on the front end. The root list carries no layout
 * class, so without it stacked Groups, which keep no outer margin of
 * their own (#1040), would touch. The site editor skips it because a
 * template's root blocks aren't in a layout container on the front end
 * either.
 */
export const POST_EDITOR_FRAMING_STYLES = `
.editor-styles-wrapper .block-editor-block-list__layout.is-root-container {
    padding: 48px 24px 0;
}

.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright) {
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
}

:where(.editor-styles-wrapper .block-editor-block-list__layout.is-root-container) > * + :where(:not(.block-list-appender)) {
    margin-block-start: var(--wp--style--block-gap, 24px);
}
`;

/**
 * Composed-view chrome styles (#655), injected into the canvas iframe.
 *
 * The chrome previews render as siblings of the block list *inside* the
 * canvas, so they inherit {@link POST_EDITOR_FRAMING_STYLES} — 48px of
 * editor padding, a 720px content clamp and the root block gap. Those
 * exist to frame post *content*; applying them to a site header or footer
 * would cap full-bleed chrome at the content measure and misrepresent the layout
 * the author is trying to check. Reset them inside the chrome regions and
 * let the theme's own layout rules decide.
 *
 * The boundary rules give a quiet visual seam between read-only chrome and
 * the editable canvas. `useDisabled()` already makes the chrome inert; this
 * is only about making the edge legible — the labelled ribbon is #623.
 */
export const COMPOSED_CHROME_STYLES = `
.ap-visual-editor__chrome .block-editor-block-list__layout.is-root-container {
    padding: 0;
}

.ap-visual-editor__chrome .block-editor-block-list__layout.is-root-container > .wp-block:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright) {
    max-width: none;
    margin-left: 0;
    margin-right: 0;
}

:where(.ap-visual-editor__chrome .block-editor-block-list__layout.is-root-container) > * + :where(:not(.block-list-appender)) {
    margin-block-start: 0;
}

.ap-visual-editor__chrome {
    position: relative;
    user-select: none;
}

.ap-visual-editor__chrome[data-chrome-region='header'] {
    border-bottom: 1px dashed color-mix(in oklab, currentColor 20%, transparent);
}

.ap-visual-editor__chrome[data-chrome-region='footer'] {
    border-top: 1px dashed color-mix(in oklab, currentColor 20%, transparent);
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
    /*
     * #1042 — the content search behind `LinkControl` and `URLInput`.
     * WordPress gets this from core-data, which this package replaces
     * with a shim, so without it the link selector only takes typed URLs.
     */
    __experimentalFetchLinkSuggestions: fetchLinkSuggestions,
    alignWide: true,
    /*
     * #791 — dock the block toolbar into the editor chrome so it never
     * escapes the editor viewport when the canvas is scrolled.
     *
     * With the default `hasFixedToolbar: false`, Gutenberg renders the
     * per-block toolbar as a floating popover positioned by
     * `useBlockToolbarPopoverProps`. That hook only re-evaluates flip /
     * shift on `blockIndex` change + ResizeObserver — there is no scroll
     * listener on the canvas content element (the hook even tries to
     * subscribe with `addEventHandler`, which is not a real DOM method).
     * So when the canvas iframe scrolls the selected block up to the top
     * of its viewport, the popover keeps its `flip: false` default and
     * ends up rendered *above* the editor's top edge, overlaying the
     * admin chrome. Setting `hasFixedToolbar: true` makes Gutenberg skip
     * the floating popover entirely; we render `<BlockToolbar />` in a
     * fixed bar inside the shell (see `editor/editor-app.tsx` and
     * `site-editor/entity-editor-canvas.tsx`) so the toolbar always
     * stays inside the editor bounds.
     */
    hasFixedToolbar: true,
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
     * #809 — seed pattern for `core/navigation`'s Create Overlay action.
     * Without it upstream falls back to `createBlock('core/paragraph')`,
     * which recurses forever here because neither `core/paragraph` nor
     * `core/missing` is registered. See `editor/navigation-overlay-pattern.ts`.
     */
    __experimentalBlockPatterns: [getNavigationOverlayPattern()],
    /*
     * #1062 — Custom HTML's editor reads this to offer its CSS and JS
     * tabs. A getter, because the mount sets the access after this module
     * loads. It only shapes the editor; every save re-checks the gate.
     */
    get __experimentalCanUserUseUnfilteredHTML(): boolean {
        return canUseUnfilteredHtml();
    },
    /*
     * Default canvas stylesheet (`{ css }`) gives blocks a typographic
     * baseline so headings/paragraphs/links visually differentiate
     * without a theme.json. Themes ship their own `{ css, baseURL }`
     * entries via the theme.json bridge (planned) — they're appended
     * to this array and win on cascade.
     */
    styles: [{ css: DEFAULT_CANVAS_STYLES }, { css: CANVAS_LAYOUT_STYLES }],
    // Top-level legacy keys are still read by some core blocks that
    // haven't migrated to `__experimentalFeatures`. Sourced from the
    // effective preset arrays so host-registered additions (#773) reach
    // the legacy code paths too.
    colors: EFFECTIVE_PALETTE,
    fontSizes: EFFECTIVE_FONT_SIZES,
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
                theme: EFFECTIVE_PALETTE,
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
            fontSizes: { theme: EFFECTIVE_FONT_SIZES, custom: [] },
            fontFamilies: { theme: EFFECTIVE_FONT_FAMILIES, custom: [] },
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
            spacingSizes: { theme: EFFECTIVE_SPACING_SIZES, custom: [] },
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
