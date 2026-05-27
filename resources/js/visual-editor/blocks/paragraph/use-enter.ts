/**
 * Paragraph — `useOnEnter` ref effect.
 *
 * Ported from `@wordpress/block-library/src/paragraph/use-enter.js`. When the
 * user hits Enter inside an empty paragraph that lives inside a wrapper block
 * declaring `__experimentalOnEnter`, the paragraph "escapes" the wrapper:
 *  - At the wrapper's last position, the paragraph is moved out one level.
 *  - In the middle, the wrapper is split in two and a new default block is
 *    inserted between the halves.
 *
 * Public-API-only port: every dependency comes from a published `@wordpress/*`
 * package. No `_shared/` vendoring required.
 */

import { useRef } from '@wordpress/element';
import { useRefEffect } from '@wordpress/compose';
import { ENTER } from '@wordpress/keycodes';
import { useSelect, useDispatch, useRegistry } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
    hasBlockSupport,
    createBlock,
    getDefaultBlockName,
} from '@wordpress/blocks';

interface UseOnEnterProps {
    readonly clientId: string;
    readonly content: string;
}

export function useOnEnter(props: UseOnEnterProps): (node: HTMLElement | null) => void {
    const { batch } = useRegistry();
    const {
        moveBlocksToPosition,
        replaceInnerBlocks,
        duplicateBlocks,
        insertBlock,
    } = useDispatch(blockEditorStore);
    const {
        getBlockRootClientId,
        getBlockIndex,
        getBlockOrder,
        getBlockName,
        getBlock,
        getNextBlockClientId,
        canInsertBlockType,
    } = useSelect(blockEditorStore);
    const propsRef = useRef<UseOnEnterProps>(props);
    propsRef.current = props;

    return useRefEffect((element: HTMLElement) => {
        function onKeyDown(event: KeyboardEvent): void {
            if (event.defaultPrevented) {
                return;
            }

            if (event.keyCode !== ENTER) {
                return;
            }

            const { content, clientId } = propsRef.current;

            if (content.length) {
                return;
            }

            const wrapperClientId = getBlockRootClientId(clientId);

            if (
                !hasBlockSupport(
                    getBlockName(wrapperClientId),
                    '__experimentalOnEnter',
                    false
                )
            ) {
                return;
            }

            const order = getBlockOrder(wrapperClientId);
            const position = order.indexOf(clientId);

            if (position === order.length - 1) {
                let newWrapperClientId: string | null = wrapperClientId;

                // Climb until the paragraph can be inserted at the
                // current ancestor's root. Upstream omits the
                // null-check; we add it defensively so a fully-rejected
                // chain (no ancestor accepts the block) breaks the loop
                // instead of looping on `getBlockRootClientId(null)`.
                while (
                    newWrapperClientId !== null &&
                    !canInsertBlockType(
                        getBlockName(clientId),
                        getBlockRootClientId(newWrapperClientId)
                    )
                ) {
                    const parent = getBlockRootClientId(newWrapperClientId);
                    if (parent === newWrapperClientId) {
                        break;
                    }
                    newWrapperClientId = parent;
                }

                if (typeof newWrapperClientId === 'string') {
                    event.preventDefault();
                    moveBlocksToPosition(
                        [clientId],
                        wrapperClientId,
                        getBlockRootClientId(newWrapperClientId),
                        getBlockIndex(newWrapperClientId) + 1
                    );
                }
                return;
            }

            const defaultBlockName = getDefaultBlockName();

            if (
                !canInsertBlockType(
                    defaultBlockName,
                    getBlockRootClientId(wrapperClientId)
                )
            ) {
                return;
            }

            event.preventDefault();

            const wrapperBlock = getBlock(wrapperClientId);
            batch(() => {
                duplicateBlocks([wrapperClientId]);
                const blockIndex = getBlockIndex(wrapperClientId);

                replaceInnerBlocks(
                    wrapperClientId,
                    wrapperBlock.innerBlocks.slice(0, position)
                );
                replaceInnerBlocks(
                    getNextBlockClientId(wrapperClientId),
                    wrapperBlock.innerBlocks.slice(position + 1)
                );
                insertBlock(
                    createBlock(defaultBlockName),
                    blockIndex + 1,
                    getBlockRootClientId(wrapperClientId),
                    true
                );
            });
        }

        element.addEventListener('keydown', onKeyDown);
        return () => {
            element.removeEventListener('keydown', onKeyDown);
        };
    }, []);
}
