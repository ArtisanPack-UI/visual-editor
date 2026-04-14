import { createClientId, type Block, type EditorStore } from '../store';
import { getBlockFactory } from './inserterRegistry';

/**
 * Creates a new block via the registered factory for `blockName` and
 * inserts it into the top-level block list. If a block is currently
 * selected the new block is placed immediately after it, otherwise it is
 * appended to the end. Returns the inserted block (or `null` when no
 * factory is registered for the given name).
 */
export function insertBlockAtSelection(
    store: EditorStore,
    blockName: string
): Block | null {
    const factory = getBlockFactory(blockName);

    if (!factory) {
        return null;
    }

    const newBlock: Block = {
        clientId: createClientId(),
        ...factory(),
    };

    const state = store.getState();
    let insertIndex = state.blocks.length;

    if (state.selection.clientId) {
        const currentIndex = state.blocks.findIndex(
            (block) => block.clientId === state.selection.clientId
        );

        if (currentIndex !== -1) {
            const edge = state.selection.edge ?? 'end';
            insertIndex = edge === 'start' ? currentIndex : currentIndex + 1;
        }
    }

    state.insertBlock(newBlock, { index: insertIndex });
    state.select(newBlock.clientId, 'start');

    return newBlock;
}

/**
 * Replaces the block identified by `clientId` with a brand-new block
 * produced from the factory registered for `blockName`, committing both
 * operations atomically via `replaceBlocks` so undo reverses the
 * replacement in a single step. Used by the slash command inserter.
 */
export function replaceBlockWithInserterBlock(
    store: EditorStore,
    clientId: string,
    blockName: string
): Block | null {
    const factory = getBlockFactory(blockName);

    if (!factory) {
        return null;
    }

    const state = store.getState();
    const currentIndex = state.blocks.findIndex(
        (block) => block.clientId === clientId
    );

    if (currentIndex === -1) {
        return null;
    }

    const newBlock: Block = {
        clientId: createClientId(),
        ...factory(),
    };

    const nextBlocks = state.blocks.slice();
    nextBlocks.splice(currentIndex, 1, newBlock);

    state.replaceBlocks(nextBlocks);
    state.select(newBlock.clientId, 'start');

    return newBlock;
}
