/**
 * List item — indent hook.
 *
 * TypeScript port of
 * `@wordpress/block-library/src/list-item/hooks/use-indent-list-item.js`
 * (v9.43.0), with `core/list` swapped for the fork's name. Moves the item
 * (or the multi-selected items) into a nested list under the previous
 * sibling (#1101).
 */

import { useCallback } from 'react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { cloneBlock, createBlock } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';

import listMetadata from '../../list/block.json';

const { name: LIST_BLOCK } = listMetadata;

interface SelectionPoint {
    readonly clientId?: string;
    readonly attributeKey?: string;
    readonly offset?: number;
}

interface BlockEditorSelectors {
    getBlock(clientId: string): BlockInstance;
    getPreviousBlockClientId(clientId: string): string | null;
    getSelectionStart(): SelectionPoint;
    getSelectionEnd(): SelectionPoint;
    hasMultiSelection(): boolean;
    getMultiSelectedBlockClientIds(): string[];
}

interface BlockEditorActions {
    replaceBlocks(clientIds: string | string[], blocks: BlockInstance[]): void;
    selectionChange(
        clientId: string,
        attributeKey?: string,
        startOffset?: number,
        endOffset?: number
    ): void;
    multiSelect(startClientId: string, endClientId: string): void;
}

/**
 * Returns a callback that nests the item (or the multi-selected items)
 * under the previous sibling's last nested list, creating one if needed.
 *
 * @param clientId The list item's client ID.
 *
 * @return A callback that returns true once indented, false when there is
 *         no previous sibling to nest under.
 */
export default function useIndentListItem(
    clientId: string
): () => boolean {
    const { replaceBlocks, selectionChange, multiSelect } = useDispatch(
        blockEditorStore
    ) as unknown as BlockEditorActions;
    const {
        getBlock,
        getPreviousBlockClientId,
        getSelectionStart,
        getSelectionEnd,
        hasMultiSelection,
        getMultiSelectedBlockClientIds,
    } = useSelect(blockEditorStore) as unknown as BlockEditorSelectors;

    return useCallback(() => {
        const previousSiblingId = getPreviousBlockClientId(clientId);

        // Upstream assumes a previous sibling; the first item has nowhere
        // to nest, so bail out instead of throwing.
        if (!previousSiblingId) {
            return false;
        }

        const isMultiSelection = hasMultiSelection();
        const clientIds = isMultiSelection
            ? getMultiSelectedBlockClientIds()
            : [clientId];
        const clonedBlocks = clientIds.map((id) => cloneBlock(getBlock(id)));
        const newListItem = cloneBlock(getBlock(previousSiblingId));

        // Without a nested list on the sibling, start a new one.
        if (!newListItem.innerBlocks?.length) {
            newListItem.innerBlocks = [createBlock(LIST_BLOCK)];
        }

        // A list item usually holds one nested list but can hold more;
        // keep them all and append to the last.
        newListItem.innerBlocks[
            newListItem.innerBlocks.length - 1
        ].innerBlocks.push(...clonedBlocks);

        // Read the selection before replacing, which updates it.
        const selectionStart = getSelectionStart();
        const selectionEnd = getSelectionEnd();

        replaceBlocks([previousSiblingId, ...clientIds], [newListItem]);

        if (!isMultiSelection) {
            selectionChange(
                clonedBlocks[0].clientId,
                selectionEnd.attributeKey,
                selectionEnd.clientId === selectionStart.clientId
                    ? selectionStart.offset
                    : selectionEnd.offset,
                selectionEnd.offset
            );
        } else {
            multiSelect(
                clonedBlocks[0].clientId,
                clonedBlocks[clonedBlocks.length - 1].clientId
            );
        }

        return true;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clientId]);
}
