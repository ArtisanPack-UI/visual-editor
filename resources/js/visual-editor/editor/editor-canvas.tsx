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

import { canvasStyles } from './canvas-styles';
import { PostTitle } from './post-title';

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
}

/**
 * Renders the post title and the iframed block canvas. Assumes a
 * `BlockEditorProvider` ancestor (mounted in `editor-app.tsx`).
 */
export function EditorCanvas(props: EditorCanvasProps): JSX.Element {
    const { showTitle, title, onTitleChange, blockContext } = props;

    const blockList = <BlockList />;

    return (
        <div
            className="ap-visual-editor__canvas"
            data-testid="ap-visual-editor-canvas"
        >
            {showTitle ? (
                <PostTitle value={title} onChange={onTitleChange} />
            ) : null}
            <div className="ap-visual-editor__canvas-frame">
                <BlockCanvas height="100%" styles={canvasStyles}>
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
