/**
 * Inert in-canvas rendering of the applied template's chrome (#655).
 *
 * Renders real Gutenberg blocks — header/footer, template parts, bindings
 * and all — inside the same iframe as the live block canvas, so the author
 * sees their content in context without leaving the editor.
 *
 * ## Why a preview provider rather than one composed tree
 *
 * The first cut at #621 fed `BlockEditorProvider` a composed tree (chrome
 * wrapped around a content slot). `BlockEditorProvider` is single-tree and
 * stateful, and every attempt to drive it that way surfaced a new failure:
 * block-validation warnings on hand-shaped chrome blocks, then an editor
 * freeze where swapping `value` echoed through `onChange` → extract →
 * state → new composed tree → new `value`, forever.
 *
 * `__experimentalUseBlockPreview` sidesteps the whole class of problem. It
 * mounts its *own* `ExperimentalBlockEditorProvider` around the chrome
 * tree, so the content provider's `value` is never touched — no echo, no
 * remount, and selection / undo history / unsaved-changes survive the
 * toggle by construction. It also:
 *
 *   - wraps the subtree in `useDisabled()`, which is what makes the chrome
 *     genuinely non-selectable and non-editable rather than merely locked;
 *   - sets `isPreviewMode`, so block edit components skip their
 *     interactive affordances;
 *   - inherits the parent's editor settings (theme styles, block supports)
 *     while clearing `styles`, because it expects to render inside a canvas
 *     whose `EditorStyles` has already emitted them. That is exactly our
 *     situation — this component mounts inside `BlockCanvas`.
 *
 * Blocks must be hydrated through `createBlock` before they get here (see
 * `hydrate.ts`); raw server JSON has no `clientId` and trips Gutenberg's
 * validation pass.
 *
 * @since 1.1.0
 */

import type { ReactElement } from 'react';
import { __experimentalUseBlockPreview as useBlockPreview } from '@wordpress/block-editor';
import type { BlockInstance } from '@wordpress/blocks';

import { ROOT_CANVAS_LAYOUT } from '../../editor-settings';

export interface ChromeBlocksProps {
    /** Hydrated chrome blocks for this region. */
    blocks: readonly BlockInstance[];
    /** Region label, surfaced to assistive tech. */
    label: string;
    /** `header` / `footer` — drives the CSS hook only. */
    region: 'header' | 'footer';
}

export function ChromeBlocks(props: ChromeBlocksProps): ReactElement | null {
    const { blocks, label, region } = props;

    if (blocks.length === 0) {
        return null;
    }

    return <ChromeBlocksPreview blocks={blocks} label={label} region={region} />;
}

/**
 * Split out so the hook is never called for an empty region — the early
 * return above would otherwise make the call conditional.
 */
function ChromeBlocksPreview(props: ChromeBlocksProps): ReactElement {
    const { blocks, label, region } = props;

    const previewProps = useBlockPreview({
        blocks,
        // Matching the canvas layout keeps chrome alignment consistent with
        // the content below it; without it the preview falls back to a
        // default flow layout and constrained sections lose their measure.
        layout: ROOT_CANVAS_LAYOUT,
        props: {
            className: 'ap-visual-editor__chrome',
            'data-chrome-region': region,
            'aria-label': label,
            // `group`, not `presentation`: ARIA prohibits `aria-label` on a
            // presentational role, and the label ("Template header
            // (read-only)") is exactly what a screen-reader user needs to
            // tell inert template chrome from the content they are editing.
            role: 'group',
        },
    } as Parameters<typeof useBlockPreview>[0]);

    return <div {...previewProps} />;
}
