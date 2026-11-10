/**
 * List item — Space/Tab indent hook.
 *
 * TypeScript port of
 * `@wordpress/block-library/src/list-item/hooks/use-space.js` (v9.43.0).
 * Tab (or Space) at the very start of an item indents it under the previous
 * sibling, and Shift+Tab there outdents it (#1101).
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { useRefEffect } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { SPACE, TAB } from '@wordpress/keycodes';

import useIndentListItem from './use-indent-list-item';
import useOutdentListItem from './use-outdent-list-item';

interface SelectionPoint {
    readonly offset?: number;
}

interface BlockEditorSelectors {
    getSelectionStart(): SelectionPoint;
    getSelectionEnd(): SelectionPoint;
    getBlockIndex(clientId: string): number;
}

/**
 * Returns a ref that indents the item on Tab or Space at its very start,
 * and outdents it on Shift+Tab there.
 *
 * @param clientId The list item's client ID.
 *
 * @return A ref callback for the item's RichText element.
 */
export default function useSpace(
    clientId: string
): (node: HTMLElement | null) => void {
    const { getSelectionStart, getSelectionEnd, getBlockIndex } = useSelect(
        blockEditorStore
    ) as unknown as BlockEditorSelectors;
    const indentListItem = useIndentListItem(clientId);
    const outdentListItem = useOutdentListItem();

    return useRefEffect(
        (element: HTMLElement) => {
            function onKeyDown(event: KeyboardEvent): void {
                const { keyCode, shiftKey, altKey, metaKey, ctrlKey } = event;

                if (
                    event.defaultPrevented ||
                    (keyCode !== SPACE && keyCode !== TAB) ||
                    // Only override when no modifiers are pressed.
                    altKey ||
                    metaKey ||
                    ctrlKey
                ) {
                    return;
                }

                const selectionStart = getSelectionStart();
                const selectionEnd = getSelectionEnd();

                if (selectionStart.offset !== 0 || selectionEnd.offset !== 0) {
                    return;
                }

                if (shiftKey) {
                    // Backspace at the start is handled by `useMerge`.
                    if (keyCode === TAB && outdentListItem()) {
                        event.preventDefault();
                    }
                } else if (getBlockIndex(clientId) !== 0) {
                    if (indentListItem()) {
                        event.preventDefault();
                    }
                }
            }

            element.addEventListener('keydown', onKeyDown);

            return () => {
                element.removeEventListener('keydown', onKeyDown);
            };
        },
        [clientId, indentListItem]
    );
}
