/**
 * List item — merge hook.
 *
 * TypeScript port of `@wordpress/block-library/src/list-item/hooks/use-merge.js`
 * (v9.43.0), with `core/list-item` swapped for the fork's name. A list item's
 * neighbours aren't always its siblings: the line above can be a nested item,
 * and the line below can sit in a parent list. This walks the visible lines,
 * then merges or outdents, so Backspace and Delete match upstream (#1043).
 *
 * Unlike upstream, a merge into or out of an item whose text is bound to
 * data only moves the cursor: the bound value hides the stored text the
 * merge would write to. The bindings HOC guards the `mergeBlocks` prop, but
 * this hook dispatches the action itself.
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { isUnmodifiedBlock } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';

import { hasBoundRichText } from '../../../bindings/bound-rich-text';
import metadata from '../block.json';
import useOutdentListItem from './use-outdent-list-item';

const { name: LIST_ITEM_BLOCK } = metadata;

interface BlockEditorSelectors {
    getPreviousBlockClientId(clientId: string): string | null;
    getNextBlockClientId(clientId: string): string | null;
    getBlockOrder(rootClientId: string): string[];
    getBlockRootClientId(clientId: string): string | null;
    getBlockName(clientId: string): string | null;
    getBlock(clientId: string): BlockInstance;
    getBlockAttributes(clientId: string): Record<string, unknown> | null;
}

interface BlockEditorActions {
    mergeBlocks(firstClientId: string, secondClientId: string): void;
    moveBlocksToPosition(
        clientIds: string[],
        fromRootClientId: string | null,
        toRootClientId: string | null,
        index?: number
    ): void;
    removeBlock(clientId: string, selectPrevious?: boolean): void;
    selectBlock(clientId: string, initialPosition?: number): void;
}

export default function useMerge(
    clientId: string,
    onMerge?: (forward?: boolean) => void
): (forward?: boolean) => void {
    const registry = useRegistry();
    const {
        getPreviousBlockClientId,
        getNextBlockClientId,
        getBlockOrder,
        getBlockRootClientId,
        getBlockName,
        getBlock,
        getBlockAttributes,
    } = useSelect(blockEditorStore) as unknown as BlockEditorSelectors;
    const { mergeBlocks, moveBlocksToPosition, removeBlock, selectBlock } = useDispatch(
        blockEditorStore
    ) as unknown as BlockEditorActions;
    const outdentListItem = useOutdentListItem();

    function getTrailingId(id: string): string {
        const order = getBlockOrder(id);

        if (!order.length) {
            return id;
        }

        return getTrailingId(order[order.length - 1]);
    }

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

    /**
     * Return the next list item with respect to the given list item. If none,
     * return the next list item of the parent list item if it exists.
     */
    function getNextSiblingOrAncestorSiblingId(id: string): string | undefined {
        const next = getNextBlockClientId(id);

        if (next) {
            return next;
        }

        const parentListItemId = getParentListItemId(id);

        if (!parentListItemId) {
            return undefined;
        }

        return getNextSiblingOrAncestorSiblingId(parentListItemId);
    }

    /**
     * Return the list item on the next line, regardless of indentation level.
     */
    function getNextId(id: string): string | undefined {
        const order = getBlockOrder(id);

        if (!order.length) {
            return getNextSiblingOrAncestorSiblingId(id);
        }

        return getBlockOrder(order[0])[0];
    }

    function isBound(id: string): boolean {
        return hasBoundRichText(getBlockAttributes(id), getBlockName(id));
    }

    return (forward) => {
        function mergeWithNested(clientIdA: string, clientIdB: string): void {
            // Either side bound: move the cursor to the other line instead.
            if (isBound(clientIdA) || isBound(clientIdB)) {
                if (forward) {
                    selectBlock(clientIdB, 0);
                } else {
                    selectBlock(clientIdA, -1);
                }

                return;
            }

            registry.batch(() => {
                // When merging a sub list item with a higher next list item,
                // any nested list items have to move too.
                const [nestedListClientId] = getBlockOrder(clientIdB);

                if (nestedListClientId) {
                    // Merging with a previous item that has no nested list:
                    // hand it the whole nested list.
                    if (
                        getPreviousBlockClientId(clientIdB) === clientIdA &&
                        !getBlockOrder(clientIdA).length
                    ) {
                        moveBlocksToPosition(
                            [nestedListClientId],
                            clientIdB,
                            clientIdA
                        );
                    } else {
                        moveBlocksToPosition(
                            getBlockOrder(nestedListClientId),
                            nestedListClientId,
                            getBlockRootClientId(clientIdA)
                        );
                    }
                }

                mergeBlocks(clientIdA, clientIdB);
            });
        }

        if (forward) {
            const nextBlockClientId = getNextId(clientId);

            if (!nextBlockClientId) {
                onMerge?.(forward);
                return;
            }

            if (getParentListItemId(nextBlockClientId)) {
                outdentListItem(nextBlockClientId);
            } else {
                mergeWithNested(clientId, nextBlockClientId);
            }

            return;
        }

        // Merging is only done from the top level. For lower levels, the list
        // item is outdented instead.
        if (getParentListItemId(clientId)) {
            outdentListItem(clientId);
            return;
        }

        const previousBlockClientId = getPreviousBlockClientId(clientId);

        if (previousBlockClientId) {
            mergeWithNested(getTrailingId(previousBlockClientId), clientId);
            return;
        }

        const blockOrder = getBlockOrder(clientId);

        if (
            isUnmodifiedBlock(getBlock(clientId), 'content') &&
            blockOrder.length > 0
        ) {
            registry.batch(() => {
                outdentListItem(getBlockOrder(blockOrder[0]));
                removeBlock(clientId, true);
            });
        } else {
            onMerge?.(forward);
        }
    };
}
