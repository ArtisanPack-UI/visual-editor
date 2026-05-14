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

import { DEFAULT_CANVAS_STYLES } from '../editor-settings';

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
export const canvasStyles: readonly CanvasStyle[] = [
    { css: canvasThemeTokens },
    { css: componentsStyle },
    { css: blockEditorStyle },
    { css: blockEditorContent },
    { css: blockLibraryStyle },
    { css: blockLibraryEditor },
    { css: DEFAULT_CANVAS_STYLES },
];
