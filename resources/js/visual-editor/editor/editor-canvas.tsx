/**
 * Post-editor canvas region — #347.
 *
 * A1 (#343) rendered the block list directly inside the admin chrome.
 * That worked, but the editor's React tree shared a DOM surface with
 * the parent admin, so high-frequency `setAttributes` dispatches (the
 * color/gradient picker dragged fast) accumulated layout-effect
 * backpressure past React's update-depth guard and crashed the
 * selected block via `BlockCrashBoundary`.
 *
 * `BlockCanvas` (`shouldIframe: true` by default) moves the block list
 * into a same-origin iframe — the same isolation `@wordpress/editor`
 * relies on. Block-support hooks measure against the iframe's stable
 * surface instead of contending with parent layout updates, so the
 * crash can't accumulate.
 *
 * Composition notes:
 *   - `PostTitle` stays *outside* the iframe. It's editor chrome, not
 *     block content — keeping it in the parent document means it picks
 *     up the shell font and the `post-title.css` rules without those
 *     having to be mirrored into the iframe.
 *   - `BlockCanvas` already wraps its children in `BlockTools`, and the
 *     `Iframe` it mounts provides `WritingFlow` internally — so this
 *     component only supplies `ObserveTyping` + `BlockList` as children.
 *   - The `BlockEditorProvider` is intentionally *not* here. It stays
 *     in `editor-app.tsx` wrapping the inserter, canvas, and inspector
 *     together so they share one `core/block-editor` registry scope
 *     (the #436 lesson — a provider mounted per-region left the
 *     inspector blind to block selection).
 */

import {
    BlockCanvas,
    BlockContextProvider,
    BlockList,
    ObserveTyping,
} from '@wordpress/block-editor';
import { useMemo } from 'react';

import { canvasStyles } from './canvas-styles';
import { PostTitle } from './post-title';
import { ROOT_CANVAS_LAYOUT } from '../editor-settings';
import { useThemeGlobalStylesCss } from '../site-editor/use-theme-global-styles-css';

/** Block context value stamped onto the canvas for cms-framework entities. */
export interface CanvasBlockContext {
    postType: string;
    postId: number;
}

export interface EditorCanvasProps {
    /** Whether the document type supports an editable title. */
    showTitle: boolean;
    /** Current title value — mirrored from the editor-app state. */
    title: string;
    /** Title change handler — stages the entity edit + save debounce. */
    onTitleChange: (value: string) => void;
    /**
     * `core/post-*` block context. Non-null only when the editor is
     * mounted against a cms-framework Post/Page; `null` for custom
     * `HasBlockContent` models and legacy fixtures, where the blocks
     * render their placeholder shell.
     */
    blockContext: CanvasBlockContext | null;
    /**
     * Visual-editor REST base. When supplied, the canvas appends the
     * active theme's compiled CSS (cms-framework's `GlobalStylesEmitter`
     * output + `themes/{slug}/style.css`) to {@link canvasStyles} so the
     * iframe matches the public front-end (Keystone #47). Omit to keep
     * the canvas on the package-default baseline.
     */
    apiBase?: string;
    /**
     * Preview width (px) for the canvas iframe container (#617).
     * When set to a positive int the canvas frame renders at that
     * width so the editor can visually preview the layout at a device
     * breakpoint. `null` leaves the frame unconstrained (fills the
     * available editor area) — the `base` viewport state.
     */
    previewWidthPx?: number | null;
}

/**
 * Renders the post title and the iframed block canvas. Assumes a
 * `BlockEditorProvider` ancestor (mounted in `editor-app.tsx`).
 */
export function EditorCanvas(props: EditorCanvasProps): JSX.Element {
    const {
        showTitle,
        title,
        onTitleChange,
        blockContext,
        apiBase,
        previewWidthPx,
    } = props;

    // Keystone #47: pull the compiled theme CSS once per `apiBase` and
    // append it to the iframe's styles array so the canvas surface
    // matches the public front-end. `useThemeGlobalStylesCss` caches
    // module-level so multiple consumers (site editor + post editor)
    // share one fetch when mounted in the same SPA session.
    const themeCss = useThemeGlobalStylesCss(apiBase);
    const styles = useMemo(
        () =>
            themeCss === undefined || themeCss === ''
                ? canvasStyles
                : [...canvasStyles, { css: themeCss }],
        [themeCss]
    );

    const blockList = <BlockList layout={ROOT_CANVAS_LAYOUT} />;

    // #617 — an inline `width` (rather than `max-width`) means the
    // frame renders at exactly the requested preview size; wide
    // presets in a narrow editor scroll horizontally on the parent
    // (`.ap-visual-editor__canvas`, styled with `overflow-x: auto`)
    // instead of being clamped. `margin: 0 auto` in the stylesheet
    // centers the shrunk frame when the editor is wider than the
    // preset. Emit `data-preview-width` so tests can assert the
    // applied preset without reading inline style.
    const hasPreviewWidth =
        typeof previewWidthPx === 'number' && previewWidthPx > 0;
    const canvasFrameStyle = hasPreviewWidth
        ? { width: `${previewWidthPx}px`, maxWidth: 'none', flexShrink: 0 }
        : undefined;

    return (
        <div
            className="ap-visual-editor__canvas"
            data-testid="ap-visual-editor-canvas"
        >
            {showTitle ? (
                <PostTitle value={title} onChange={onTitleChange} />
            ) : null}
            <div
                className="ap-visual-editor__canvas-frame"
                data-preview-width={
                    hasPreviewWidth ? String(previewWidthPx) : 'base'
                }
                data-testid="ap-visual-editor-canvas-frame"
                style={canvasFrameStyle}
            >
                <BlockCanvas height="100%" styles={styles}>
                    {blockContext !== null ? (
                        <BlockContextProvider value={blockContext}>
                            <ObserveTyping>{blockList}</ObserveTyping>
                        </BlockContextProvider>
                    ) : (
                        <ObserveTyping>{blockList}</ObserveTyping>
                    )}
                </BlockCanvas>
            </div>
        </div>
    );
}
