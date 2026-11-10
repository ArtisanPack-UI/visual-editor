/**
 * List item — Enter-on-empty-item hook.
 *
 * TypeScript port of
 * `@wordpress/block-library/src/list-item/hooks/use-enter.js` (v9.43.0),
 * with `core/list-item` swapped for the fork's name. Enter on an empty item
 * outdents it when it is nested; at the top level it splits the list around
 * a new default block so the caret leaves the list (#1101).
 *
 * Unlike upstream, emptying the first item drops the head list instead of
 * leaving an item-less list behind, and an item whose text is bound to data
 * is left alone.
 */

import { useRef } from 'react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
    cloneBlock,
    createBlock,
    getDefaultBlockName,
} from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { RichTextData } from '@wordpress/rich-text';
import { useRefEffect } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { ENTER } from '@wordpress/keycodes';

import { hasBoundRichText } from '../../../bindings/bound-rich-text';
import metadata from '../block.json';
import useOutdentListItem from './use-outdent-list-item';

const { name: LIST_ITEM_BLOCK } = metadata;

interface UseEnterProps {
    readonly content: unknown;
    readonly clientId: string;
}

interface BlockEditorSelectors {
    getBlock(clientId: string): BlockInstance;
    getBlockRootClientId(clientId: string): string | null;
    getBlockIndex(clientId: string): number;
    getBlockName(clientId: string | null): string | null;
}

interface BlockEditorActions {
    replaceBlocks(
        clientIds: string | string[],
        blocks: BlockInstance[],
        indexToSelect?: number
    ): void;
    selectionChange(clientId: string): void;
}

/**
 * Returns the text length of a list item's `content`, which is either an
 * HTML string or a `RichTextData` instance (whose `length` is its text
 * length, as upstream relies on).
 */
function contentLength(content: unknown): number {
    if (content instanceof RichTextData) {
        return content.length;
    }

    return 'string' === typeof content ? content.length : 0;
}

export default function useEnter(
    props: UseEnterProps
): (node: HTMLElement | null) => void {
    const { replaceBlocks, selectionChange } = useDispatch(
        blockEditorStore
    ) as unknown as BlockEditorActions;
    const { getBlock, getBlockRootClientId, getBlockIndex, getBlockName } =
        useSelect(blockEditorStore) as unknown as BlockEditorSelectors;
    const propsRef = useRef<UseEnterProps>(props);
    propsRef.current = props;
    const outdentListItem = useOutdentListItem();

    return useRefEffect((element: HTMLElement) => {
        function onKeyDown(event: KeyboardEvent): void {
            if (event.defaultPrevented || event.keyCode !== ENTER) {
                return;
            }

            const { content, clientId } = propsRef.current;

            if (contentLength(content)) {
                return;
            }

            // A bound item shows its data while the stored text stays
            // empty, so it is never "empty" in the sense this hook means.
            if (hasBoundRichText(getBlock(clientId)?.attributes, LIST_ITEM_BLOCK)) {
                return;
            }

            const listClientId = getBlockRootClientId(clientId);

            if (!listClientId) {
                return;
            }

            event.preventDefault();

            const canOutdent =
                getBlockName(getBlockRootClientId(listClientId)) ===
                LIST_ITEM_BLOCK;

            if (canOutdent) {
                outdentListItem();
                return;
            }

            // Top-level list: split it around a new default block.
            const topParentListBlock = getBlock(listClientId);
            const blockIndex = getBlockIndex(clientId);
            // Unlike upstream, an empty head list is dropped rather than
            // left behind as an item-less list block.
            const head = blockIndex
                ? [
                      cloneBlock({
                          ...topParentListBlock,
                          innerBlocks: topParentListBlock.innerBlocks.slice(
                              0,
                              blockIndex
                          ),
                      }),
                  ]
                : [];
            const middle = createBlock(getDefaultBlockName() as string);
            // The emptied item may still own a nested list; its items move
            // into the tail list.
            const after = [
                ...(topParentListBlock.innerBlocks[blockIndex].innerBlocks[0]
                    ?.innerBlocks || []),
                ...topParentListBlock.innerBlocks.slice(blockIndex + 1),
            ];
            const tail = after.length
                ? [
                      cloneBlock({
                          ...topParentListBlock,
                          innerBlocks: after,
                      }),
                  ]
                : [];

            replaceBlocks(
                topParentListBlock.clientId,
                [...head, middle, ...tail],
                head.length
            );
            // The replaced block is the list, not the selected item, so the
            // selection has to be moved by hand.
            selectionChange(middle.clientId);
        }

        element.addEventListener('keydown', onKeyDown);

        return () => {
            element.removeEventListener('keydown', onKeyDown);
        };
    }, []);
}
