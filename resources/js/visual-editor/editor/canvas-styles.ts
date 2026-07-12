/**
 * Canvas iframe stylesheet bundle — #347.
 *
 * `editor-app.tsx` renders the block list inside a `BlockCanvas` iframe
 * (A1 follow-up: isolating the editor's React tree from the admin DOM
 * is what stops the color-picker fast-drag crash — the per-frame
 * `setAttributes` backpressure no longer contends with parent layout
 * updates past React's update-depth guard).
 *
 * The iframe document is sandboxed: none of the Vite-injected
 * `@wordpress/*` stylesheets in the parent document cross into it, and
 * `BlockCanvas`'s built-in `getCompatibilityStyles` copy skips them
 * because Vite's `<style>` tags carry no `id`. So the canvas styles
 * have to be assembled here and handed to `BlockCanvas` via its
 * `styles` prop, which injects them into the iframe through
 * `__unstableEditorStyles`.
 *
 * Each file is imported with Vite's `?inline` query so it resolves to
 * its CSS text rather than a side-effect injection into the parent
 * document. `editor-app.tsx` keeps its own side-effect imports of the
 * same `@wordpress/*` stylesheets for the editor chrome (top bar,
 * block toolbar, inspector) that renders *outside* the iframe.
 */

import blockEditorContent from '@wordpress/block-editor/build-style/content.css?inline';
import blockEditorStyle from '@wordpress/block-editor/build-style/style.css?inline';
import blockLibraryEditor from '@wordpress/block-library/build-style/editor.css?inline';
import blockLibraryStyle from '@wordpress/block-library/build-style/style.css?inline';
import componentsStyle from '@wordpress/components/build-style/style.css?inline';
import { applyFilters } from '@wordpress/hooks';

import {
    ALIGNMENT_OVERRIDE_STYLES,
    DEFAULT_CANVAS_STYLES,
    POST_EDITOR_FRAMING_STYLES,
} from '../editor-settings';

import canvasThemeTokens from './canvas-theme-tokens.css?inline';
import flexLayoutStyles from '../../../css/flex-layout.css?inline';
import photoGridStyles from '../blocks/_shared/photo-grid/photo-grid.css?inline';

/** A single stylesheet entry in the shape `BlockCanvas`'s `styles` prop expects. */
export interface CanvasStyle {
    css: string;
}

/**
 * Auto-discovered block stylesheets — #566.
 *
 * Every block ships its own `.css` file as a side-effect import from
 * its `index.ts`. That import lands in the parent document only; the
 * editor's sandboxed `BlockCanvas` iframe never sees it, so blocks
 * with their own stylesheet (callout, breadcrumbs, the interactive
 * families, future blocks) render unstyled in the canvas unless the
 * sheet is re-resolved as inline CSS and handed to `BlockCanvas`.
 *
 * The glob pattern `../blocks/*` + `/*.css` matches per-block sheets
 * (`breadcrumbs/breadcrumbs.css`, `callout/callout.css`, …) and the
 * shared baselines that live in sibling directories
 * (`_shared/social-icons.css`). Entries are sorted by path so the
 * cascade order inside the iframe is deterministic across builds.
 */
const blockStylesheetModules = import.meta.glob<string>(
    '../blocks/*/*.css',
    { eager: true, query: '?inline', import: 'default' }
);

export const blockStylesheetPaths: readonly string[] = Object.keys(
    blockStylesheetModules
).sort();

const blockStylesheets: readonly CanvasStyle[] = blockStylesheetPaths.map(
    (path) => ({ css: blockStylesheetModules[path] })
);

/**
 * Layout baseline rules — flex + grid.
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
 * Result inside the visual-editor iframe: blocks with
 * `supports.layout.default.type = "flex"` in block.json (gallery,
 * buttons, cover, group's row/stack variations) render with
 * `is-layout-flex` on the wrapper but no flex display rule, so they
 * fall back to block flow and inner blocks stack vertically.
 *
 * Ship the baseline rules statically here so the canvas matches the
 * front-end (which gets the same rules via `<x-ve-blocks-styles>`).
 * Generic `.is-layout-flex` / `.is-layout-grid` selectors mean
 * per-block compound classes (`wp-block-gallery-is-layout-flex`, …)
 * inherit without enumerating block names.
 */
const LAYOUT_BASELINE_STYLES = `
.is-layout-flex { display: flex; flex-wrap: wrap; align-items: center; }
.is-layout-flex > :is(*, div) { margin: 0; }
.is-layout-grid { display: grid; }
.is-layout-grid > :is(*, div) { margin: 0; }
`;

/**
 * Editor-only overrides for the interactive block families.
 *
 * These rules sit downstream of the block-authored stylesheets so they
 * win when the editor needs a different visual than the front-end:
 * expanded panels for editability, dropped marquee animation so the
 * RichText cursor stays anchored, etc. Keep adjustments here rather
 * than inside the block's own stylesheet so the front-end CSS stays
 * lean.
 */
const EDITOR_BLOCK_TWEAKS = `
    /* Editor preview overrides — keep every panel and tab
       section expanded so authors can edit each one without
       toggling. The front-end interactivity script restores
       normal accordion / tab behavior at render time. */
    .ap-accordion__body[hidden] { display: block !important; }
    .ap-tab-section[hidden] { display: block !important; }

    /* Drop list bullets from the tab list — Gutenberg's editor
       stylesheet adds them back inside the iframe. */
    .ap-tabs__list ul { list-style: none !important; padding-left: 0 !important; }
    .ap-tabs__list ul li { margin: 0 !important; padding: 0 !important; }

    /* Inner-block reset: Gutenberg's editor stylesheet adds
       generous top/bottom margins to headings and paragraphs
       (the "is-layout-flow" baseline), which doubles up with
       our wrapper padding. Collapse the first/last child
       margin so the accordion title + body and tab section
       line up tight against the wrapper edges in the canvas. */
    .ap-accordion__title-content > :first-child,
    .ap-accordion__body > :first-child,
    .ap-tab-section > :first-child { margin-block-start: 0 !important; }
    .ap-accordion__title-content > :last-child,
    .ap-accordion__body > :last-child,
    .ap-tab-section > :last-child { margin-block-end: 0 !important; }

    /* The block-editor wraps every block in a .block-editor-block-list__block
       div that adds its own margin. Inside the accordion / tab containers,
       trim that wrapper margin so the visual cadence matches the
       front-end. */
    .ap-accordion__body .block-editor-block-list__block,
    .ap-tab-section .block-editor-block-list__block { margin-top: 0; margin-bottom: 0; }

    /* Marquee — the front-end CSS translates the text off-screen
       and the renderers' inline animation slides it back. The
       editor preview drops the animation so authors can edit the
       RichText without it scrolling out from under their cursor;
       pin the text in place so it's readable. */
    .ap-marquee__text { transform: none !important; white-space: normal !important; }
`;

/**
 * Ordered `{ css }` entries handed to `BlockCanvas`'s `styles` prop.
 * The order is the cascade order inside the iframe:
 *
 *   1. the token bridge first, so every rule below it can read the
 *      `--wp-*` / `--color-*` custom properties;
 *   2. the `@wordpress/*` stylesheets (components → block-editor →
 *      block-library), matching the parent-document import order in
 *      `editor-app.tsx`;
 *   3. the layout baseline, then every block-authored stylesheet
 *      (auto-discovered via the `blocks/* /*.css` glob, #566), then
 *      the editor-only tweaks that override them where the iframe
 *      needs a different visual than the front-end;
 *   4. `DEFAULT_CANVAS_STYLES` last, so the package's typographic
 *      baseline wins over the Gutenberg defaults — a theme.json
 *      stylesheet will append after this entry and win in turn once
 *      the theme.json bridge lands.
 *
 * `BlockCanvas` passes this straight to `__unstableEditorStyles` with
 * no wrapper selector, so the CSS is injected into the iframe verbatim
 * (`transformStyles` is a no-op without a scope or `baseURL`).
 */
/**
 * Base ordered list. Third parties extend this via
 * `ap.visual-editor.canvas-styles` — see the export below.
 */
const baseCanvasStyles: CanvasStyle[] = [
    { css: canvasThemeTokens },
    { css: componentsStyle },
    { css: blockEditorStyle },
    { css: blockEditorContent },
    { css: blockLibraryStyle },
    { css: blockLibraryEditor },
    { css: LAYOUT_BASELINE_STYLES },
    // Flex layout utility stylesheet (#595). Lives outside the
    // `blocks/*/` tree so it isn't picked up by the glob below; hand
    // it to the iframe explicitly so `.ap-flex` and per-breakpoint
    // utilities resolve inside the canvas.
    { css: flexLayoutStyles },
    // Every block-authored stylesheet, auto-discovered via the
    // `blocks/*/*.css` glob (#566). Replaces the per-block manual
    // entries that used to sit here and forced an edit to
    // canvas-styles.ts every time a new custom block landed.
    ...blockStylesheets,
    // Photo Grid container support (#594). Lives at
    // `blocks/_shared/photo-grid/photo-grid.css` — three levels deep,
    // so the two-level `blocks/*/*.css` glob above skips it. Hand
    // it to the iframe explicitly. Loaded *after* the per-block
    // stylesheets so the cascade matches the front-end order in
    // `blocks-styles.blade.php` (block-family CSS before photo-grid).
    { css: photoGridStyles },
    { css: EDITOR_BLOCK_TWEAKS },
    { css: DEFAULT_CANVAS_STYLES },
    // Per-block wide/full overrides. Shared with the site editor —
    // both need the toolbar's alignment buttons to actually resize
    // blocks (Keystone #47).
    { css: ALIGNMENT_OVERRIDE_STYLES },
    // Post-editor framing — 720px content column applied to direct
    // children of the root layout. Site-editor canvases skip this
    // entry so templates + template parts span full-bleed like the
    // front-end (#47).
    { css: POST_EDITOR_FRAMING_STYLES },
];

/**
 * Iframe-injected stylesheet list, pipeable through the
 * `ap.visual-editor.canvas-styles` filter so third-party packages can
 * push their CSS into the canvas without editing this file.
 *
 * Filter callbacks receive the ordered `CanvasStyle[]` and must return
 * a new `CanvasStyle[]`. Non-array returns are ignored (the base list
 * is used as-is), non-object entries are dropped, and each entry's
 * `css` property must be a string — the filter is a hard boundary
 * because the array feeds directly into `BlockCanvas`'s
 * `__unstableEditorStyles` prop.
 */
export const canvasStyles: readonly CanvasStyle[] = ( (): CanvasStyle[] => {
    const filtered = applyFilters(
        'ap.visual-editor.canvas-styles',
        [...baseCanvasStyles],
    );

    if ( ! Array.isArray( filtered ) ) {
        return baseCanvasStyles;
    }

    return filtered.filter(
        ( entry ): entry is CanvasStyle =>
            entry !== null &&
            typeof entry === 'object' &&
            typeof ( entry as { css?: unknown } ).css === 'string',
    );
} )();
