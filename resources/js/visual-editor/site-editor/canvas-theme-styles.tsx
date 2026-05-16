/**
 * Inline `<style>` tag carrying the active theme's compiled CSS
 * (Keystone #47). Lives inside the `.editor-styles-wrapper` so
 * the rules cascade onto every block in the canvas — same as the
 * package's `DEFAULT_CANVAS_STYLES` baseline that sits next to it.
 *
 * Why inline instead of `BlockEditorProvider`'s `settings.styles`
 * channel: the site editor renders the block list in the parent
 * DOM tree (not in a `<BlockCanvas>` iframe — see #418). Gutenberg
 * only injects `settings.styles` into the iframe surface; for the
 * inline path the rules have to live in the DOM as a real `<style>`
 * tag.
 *
 * The CSS comes from `/global-styles/css` — server-side that's
 * cms-framework's `GlobalStylesEmitter::emit()` plus the active
 * theme's hand-authored `themes/{slug}/style.css`, concatenated in
 * cascade order so emitter rules declare `--wp--preset--*` tokens
 * and the hand-authored sheet consumes / overrides them.
 *
 * Renders nothing when no `apiBase` is wired, when the fetch is
 * still in flight, or when the response was empty — the canvas
 * stays on `DEFAULT_CANVAS_STYLES` as the floor.
 */

import { useThemeGlobalStylesCss } from './use-theme-global-styles-css';

export interface CanvasThemeStylesProps {
    /**
     * Base URL of the site-editor REST surface (e.g. `/visual-editor/api`).
     * Omit when the canvas is mounted outside an apiBase-bearing context;
     * the component then renders nothing.
     */
    apiBase?: string;
}

export function CanvasThemeStyles(props: CanvasThemeStylesProps): JSX.Element | null {
    const css = useThemeGlobalStylesCss(props.apiBase);

    if (css === undefined || css === '') {
        return null;
    }

    return <style data-testid="ap-canvas-theme-styles">{css}</style>;
}
