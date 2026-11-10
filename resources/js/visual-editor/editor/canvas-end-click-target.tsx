/**
 * Click-below-the-content target for the editing canvases (#1102).
 *
 * `BlockList` only renders its root `DefaultBlockAppender` while the
 * root is empty (or can't take the default block), so once a document
 * has content there is nothing below the last block to click. WordPress
 * covers this in `@wordpress/editor`, which this package doesn't use.
 *
 * This component sits right after the root `BlockList` and fills the
 * space below it. A primary click there puts the caret in a trailing
 * empty paragraph:
 *
 *   - when the last root block is already an unmodified default block,
 *     it is selected and focused, so repeated clicks never stack empty
 *     paragraphs;
 *   - otherwise a new default block is appended and selected (which
 *     focuses it through `useFocusFirstElement`).
 *
 * Nothing is inserted while the root is template-locked, while its
 * editing mode is `disabled`, or when the default block can't be
 * inserted at the root. The target still renders in those states so
 * the canvas keeps its bottom spacing, but without the `is-enabled`
 * class, so the stylesheet drops the text cursor and lets clicks pass
 * through to the canvas, whose selection clearer deselects as before.
 *
 * Zoom-out mode isn't guarded: it is only reachable through the
 * block editor's private API, which this package doesn't use.
 *
 * The target lives outside the block list, so the root container's
 * drop zone and the multi-selection handlers never see its events.
 * The action runs on `click` rather than `mousedown` so a drag that
 * merely starts or ends here doesn't insert anything.
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { getDefaultBlockName, isUnmodifiedDefaultBlock, type BlockInstance } from '@wordpress/blocks';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import type { MouseEvent } from 'react';

/** Root client id of the canvas's top-level block list. */
const ROOT_CLIENT_ID = '';

/** Read-only slice of `core/block-editor` selectors used here. */
interface BlockEditorSelectors {
    getBlockOrder: (rootClientId?: string) => string[];
    getBlock: (clientId: string) => BlockInstance | null;
    getSelectedBlockClientId: () => string | null;
    getTemplateLock: (rootClientId?: string) => string | false;
    getBlockEditingMode: (clientId?: string) => string;
    canInsertBlockType: (blockName: string, rootClientId?: string) => boolean;
}

/** `core/block-editor` actions used here. */
interface BlockEditorActions {
    selectBlock: (clientId: string, initialPosition?: number) => void;
    insertDefaultBlock: (
        attributes?: Record<string, unknown>,
        rootClientId?: string,
        index?: number
    ) => void;
}

export interface CanvasEndClickTargetProps {
    /**
     * Shrinks the target to a short strip. The composed view uses it so
     * the read-only footer chrome isn't pushed far below the content.
     */
    compact?: boolean;
}

/**
 * Whether the default block can be added at the end of the root list.
 */
function canAppendDefaultBlock(selectors: BlockEditorSelectors): boolean {
    const defaultBlockName = getDefaultBlockName();

    if (defaultBlockName === null || defaultBlockName === undefined) {
        return false;
    }

    if (selectors.getTemplateLock(ROOT_CLIENT_ID)) {
        return false;
    }

    if ('disabled' === selectors.getBlockEditingMode(ROOT_CLIENT_ID)) {
        return false;
    }

    return selectors.canInsertBlockType(defaultBlockName, ROOT_CLIENT_ID);
}

/**
 * Renders the empty area below the root block list that appends (or
 * focuses) a trailing empty paragraph when clicked.
 */
export function CanvasEndClickTarget(props: CanvasEndClickTargetProps): JSX.Element {
    const { compact = false } = props;
    const registry = useRegistry();
    const isEnabled = useSelect(
        (select) => canAppendDefaultBlock(select(blockEditorStore) as unknown as BlockEditorSelectors),
        []
    );
    const { selectBlock, insertDefaultBlock } = useDispatch(blockEditorStore) as unknown as BlockEditorActions;

    const onClick = (event: MouseEvent<HTMLDivElement>): void => {
        if (0 !== event.button) {
            return;
        }

        // Re-read the store at click time: `isEnabled` can lag a render
        // behind a lock change made in the same tick.
        const selectors = registry.select(blockEditorStore) as unknown as BlockEditorSelectors;

        if (!canAppendDefaultBlock(selectors)) {
            return;
        }

        const order = selectors.getBlockOrder(ROOT_CLIENT_ID);
        const lastClientId = order[order.length - 1];
        const lastBlock = lastClientId === undefined ? null : selectors.getBlock(lastClientId);

        if (lastBlock !== null && isUnmodifiedDefaultBlock(lastBlock)) {
            if (selectors.getSelectedBlockClientId() === lastBlock.clientId) {
                // Already selected: `selectBlock` would be a no-op and
                // `useFocusFirstElement` wouldn't re-run, so focus the
                // block directly.
                const element = event.currentTarget.ownerDocument.querySelector<HTMLElement>(
                    `[data-block="${lastBlock.clientId}"]`
                );

                element?.focus();

                return;
            }

            selectBlock(lastBlock.clientId);

            return;
        }

        insertDefaultBlock({}, ROOT_CLIENT_ID, order.length);
    };

    const className = [
        'ap-canvas-end-click-target',
        compact ? 'ap-canvas-end-click-target--compact' : '',
        isEnabled ? 'is-enabled' : '',
    ]
        .filter((name) => name !== '')
        .join(' ');

    return (
        // Pointer-only affordance: keyboard users continue writing with
        // Enter / the inserter, so the target stays out of the a11y tree.
        <div
            aria-hidden="true"
            className={className}
            data-testid="ap-canvas-end-click-target"
            onClick={isEnabled ? onClick : undefined}
        />
    );
}
