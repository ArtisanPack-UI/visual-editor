import { useMemo } from 'react';
import { createStore, useStore, type StoreApi } from 'zustand';
import type {
    Block,
    EditorStoreState,
    InsertLocation,
    MoveLocation,
    Selection,
    SelectionEdge,
} from './types';
import {
    findBlock,
    findChildren,
    insertIntoTree,
    removeFromTree,
    updateAttributesInTree,
} from './treeUtils';

export type EditorStore = StoreApi<EditorStoreState>;

const initialSelection: Selection = { clientId: null };

export function createEditorStore(initialBlocks: Block[] = []): EditorStore {
    return createStore<EditorStoreState>((set) => ({
        blocks: initialBlocks,
        selection: initialSelection,
        isDirty: false,

        insertBlock: (block: Block, location: InsertLocation = {}) => {
            set((state) => ({
                blocks: insertIntoTree(
                    state.blocks,
                    block,
                    location.parentClientId ?? null,
                    location.index
                ),
                isDirty: true,
            }));
        },

        updateBlockAttributes: (clientId: string, attrs: Record<string, unknown>) => {
            set((state) => {
                const nextBlocks = updateAttributesInTree(state.blocks, clientId, attrs);

                if (nextBlocks === state.blocks) {
                    return state;
                }

                return { ...state, blocks: nextBlocks, isDirty: true };
            });
        },

        removeBlock: (clientId: string) => {
            set((state) => {
                const nextBlocks = removeFromTree(state.blocks, clientId);

                if (nextBlocks === state.blocks) {
                    return state;
                }

                const nextSelection =
                    state.selection.clientId === clientId ? initialSelection : state.selection;

                return {
                    ...state,
                    blocks: nextBlocks,
                    selection: nextSelection,
                    isDirty: true,
                };
            });
        },

        moveBlock: (clientId: string, location: MoveLocation) => {
            set((state) => {
                const parentClientId = location.parentClientId ?? null;

                if (parentClientId !== null) {
                    return state;
                }

                const currentIndex = state.blocks.findIndex(
                    (block) => block.clientId === clientId
                );

                if (currentIndex === -1) {
                    return state;
                }

                const block = state.blocks[currentIndex];
                const withoutBlock = state.blocks.slice();

                withoutBlock.splice(currentIndex, 1);

                const targetIndex =
                    location.index < 0
                        ? 0
                        : location.index > withoutBlock.length
                            ? withoutBlock.length
                            : location.index;

                withoutBlock.splice(targetIndex, 0, block);

                if (targetIndex === currentIndex) {
                    return state;
                }

                return { ...state, blocks: withoutBlock, isDirty: true };
            });
        },

        replaceBlocks: (newBlocks: Block[]) => {
            set((state) => ({
                ...state,
                blocks: newBlocks,
                selection: initialSelection,
                isDirty: true,
            }));
        },

        select: (clientId: string, edge?: SelectionEdge) => {
            set((state) => ({ ...state, selection: { clientId, edge } }));
        },

        clearSelection: () => {
            set((state) => ({ ...state, selection: initialSelection }));
        },

        markDirty: () => {
            set((state) => (state.isDirty ? state : { ...state, isDirty: true }));
        },

        markClean: () => {
            set((state) => (state.isDirty ? { ...state, isDirty: false } : state));
        },
    }));
}

export function useBlock(store: EditorStore, clientId: string | null): Block | undefined {
    const blocks = useStore(store, (state) => state.blocks);

    return useMemo(() => {
        if (clientId === null) {
            return undefined;
        }

        return findBlock(blocks, clientId);
    }, [blocks, clientId]);
}

export function useChildren(store: EditorStore, parentClientId: string | null): Block[] {
    const blocks = useStore(store, (state) => state.blocks);

    return useMemo(() => findChildren(blocks, parentClientId), [blocks, parentClientId]);
}

export function useSelection(store: EditorStore): Selection {
    return useStore(store, (state) => state.selection);
}

export function useIsDirty(store: EditorStore): boolean {
    return useStore(store, (state) => state.isDirty);
}
