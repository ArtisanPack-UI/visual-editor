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

import {
    ALIGNMENT_OVERRIDE_STYLES,
    DEFAULT_CANVAS_STYLES,
    POST_EDITOR_FRAMING_STYLES,
} from '../editor-settings';

import accordionStyles from '../blocks/accordion/accordion.css?inline';
import gridStyles from '../blocks/grid/grid.css?inline';
import marqueeStyles from '../blocks/marquee/marquee.css?inline';
import tabsStyles from '../blocks/tabs/tabs.css?inline';

import canvasThemeTokens from './canvas-theme-tokens.css?inline';

/** A single stylesheet entry in the shape `BlockCanvas`'s `styles` prop expects. */
export interface CanvasStyle {
    css: string;
}

/**
 * Ordered `{ css }` entries handed to `BlockCanvas`'s `styles` prop.
 * The order is the cascade order inside the iframe:
 *
 *   1. the token bridge first, so every rule below it can read the
 *      `--wp-*` / `--color-*` custom properties;
 *   2. the `@wordpress/*` stylesheets (components → block-editor →
 *      block-library), matching the parent-document import order in
 *      `editor-app.tsx`;
 *   3. `DEFAULT_CANVAS_STYLES` last, so the package's typographic
 *      baseline wins over the Gutenberg defaults — a theme.json
 *      stylesheet will append after this entry and win in turn once
 *      the theme.json bridge lands.
 *
 * `BlockCanvas` passes this straight to `__unstableEditorStyles` with
 * no wrapper selector, so the CSS is injected into the iframe verbatim
 * (`transformStyles` is a no-op without a scope or `baseURL`).
 */
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

export const canvasStyles: readonly CanvasStyle[] = [
    { css: canvasThemeTokens },
    { css: componentsStyle },
    { css: blockEditorStyle },
    { css: blockEditorContent },
    { css: blockLibraryStyle },
    { css: blockLibraryEditor },
    { css: LAYOUT_BASELINE_STYLES },
    // Interactive block families (#497) — accordion + tabs.
    // The editor canvas runs in a sandboxed iframe so the per-block
    // CSS imports in `blocks/{accordion,tabs}/index.ts` don't reach
    // it; ship the rules here too so authors get an accurate preview.
    // Editor-specific overrides (showing all panels at once for
    // editability) are appended after the front-end rules.
    { css: accordionStyles },
    { css: tabsStyles },
    // Grid family (#498) — same iframe-doesn't-see-`?inline` story as
    // accordion/tabs above. Ship the rules here so authors get an
    // accurate preview of the responsive column layout.
    { css: gridStyles },
    // Marquee block (#500) — keyframes + wrapper baseline. Same
    // iframe-isolation story: the per-block import in
    // `blocks/marquee/index.ts` lands in the parent document only.
    { css: marqueeStyles },
    {
        css: `
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

            /* Marquee (#500) — the front-end CSS translates the text
               off-screen and the renderers' inline animation slides it
               back. The editor preview drops the animation so authors
               can edit the RichText without it scrolling out from under
               their cursor; pin the text in place so it's readable. */
            .ap-marquee__text { transform: none !important; white-space: normal !important; }
        `,
    },
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
