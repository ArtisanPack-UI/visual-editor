/**
 * List item — outdent hook.
 *
 * TypeScript port of
 * `@wordpress/block-library/src/list-item/hooks/use-outdent-list-item.js`
 * (v9.43.0), with `core/list-item` swapped for the fork's name. `useMerge`
 * calls it to lift a nested item up a level instead of merging it (#1043).
 */

import { useCallback } from 'react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { cloneBlock } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';

import metadata from '../block.json';

const { name: LIST_ITEM_BLOCK } = metadata;

interface BlockEditorSelectors {
    getBlockRootClientId(clientId: string): string | null;
    getBlockName(clientId: string): string | null;
    getBlockOrder(rootClientId: string): string[];
    getBlockIndex(clientId: string): number;
    getSelectedBlockClientIds(): string[];
    getBlock(clientId: string): BlockInstance;
    getBlockListSettings(clientId: string): Record<string, unknown>;
}

interface BlockEditorActions {
    moveBlocksToPosition(
        clientIds: string[],
        fromRootClientId: string | null,
        toRootClientId: string | null,
        index?: number
    ): void;
    removeBlock(clientId: string, selectPrevious?: boolean): void;
    insertBlock(
        block: BlockInstance,
        index: number,
        rootClientId: string,
        updateSelection: boolean
    ): void;
    updateBlockListSettings(
        clientId: string,
        settings: Record<string, unknown>
    ): void;
}

export default function useOutdentListItem(): (
    clientIds?: string | string[]
) => true | undefined {
    const registry = useRegistry();
    const {
        moveBlocksToPosition,
        removeBlock,
        insertBlock,
        updateBlockListSettings,
    } = useDispatch(blockEditorStore) as unknown as BlockEditorActions;
    const {
        getBlockRootClientId,
        getBlockName,
        getBlockOrder,
        getBlockIndex,
        getSelectedBlockClientIds,
        getBlock,
        getBlockListSettings,
    } = useSelect(blockEditorStore) as unknown as BlockEditorSelectors;

    function getParentListItemId(id: string): string | undefined {
        const listId = getBlockRootClientId(id);
        const parentListItemId = listId ? getBlockRootClientId(listId) : null;

        if (!parentListItemId) {
            return undefined;
        }

        if (getBlockName(parentListItemId) !== LIST_ITEM_BLOCK) {
            return undefined;
        }

        return parentListItemId;
    }

    return useCallback((clientIds = getSelectedBlockClientIds()) => {
        const ids = Array.isArray(clientIds) ? clientIds : [clientIds];

        if (!ids.length) {
            return undefined;
        }

        const firstClientId = ids[0];

        if (getBlockName(firstClientId) !== LIST_ITEM_BLOCK) {
            return undefined;
        }

        const parentListItemId = getParentListItemId(firstClientId);

        if (!parentListItemId) {
            return undefined;
        }

        const parentListId = getBlockRootClientId(firstClientId) as string;
        const lastClientId = ids[ids.length - 1];
        const order = getBlockOrder(parentListId);
        const followingListItems = order.slice(
            getBlockIndex(lastClientId) + 1
        );

        registry.batch(() => {
            if (followingListItems.length) {
                let nestedListId = getBlockOrder(firstClientId)[0];

                if (!nestedListId) {
                    const nestedListBlock = cloneBlock(
                        getBlock(parentListId),
                        {},
                        []
                    );
                    nestedListId = nestedListBlock.clientId;
                    insertBlock(nestedListBlock, 0, firstClientId, false);
                    // Immediately update the block list settings, otherwise
                    // blocks can't be moved here due to canInsert checks.
                    updateBlockListSettings(
                        nestedListId,
                        getBlockListSettings(parentListId)
                    );
                }

                moveBlocksToPosition(
                    followingListItems,
                    parentListId,
                    nestedListId
                );
            }

            moveBlocksToPosition(
                ids,
                parentListId,
                getBlockRootClientId(parentListItemId),
                getBlockIndex(parentListItemId) + 1
            );

            if (!getBlockOrder(parentListId).length) {
                removeBlock(parentListId, false);
            }
        });

        return true;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
}
