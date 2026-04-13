import { useMemo } from 'react';
import { createStore, useStore, type StoreApi } from 'zustand';
import type {
    Block,
    EditorStoreState,
    HistoryState,
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

const HISTORY_LIMIT = 100;
const COALESCE_WINDOW_MS = 500;

function selectionExistsIn(blocks: Block[], clientId: string | null): boolean {
    if (clientId === null) {
        return true;
    }

    return findBlock(blocks, clientId) !== undefined;
}

function createInitialHistory(): HistoryState {
    return {
        past: [],
        future: [],
        lastCoalesceKey: null,
        lastCoalesceAt: 0,
    };
}

function commitHistory(
    history: HistoryState,
    previousBlocks: Block[],
    coalesceKey: string | null,
    now: number
): HistoryState {
    const canCoalesce =
        coalesceKey !== null &&
        history.past.length > 0 &&
        history.lastCoalesceKey === coalesceKey &&
        now - history.lastCoalesceAt < COALESCE_WINDOW_MS;

    if (canCoalesce) {
        return {
            past: history.past,
            future: [],
            lastCoalesceKey: coalesceKey,
            lastCoalesceAt: now,
        };
    }

    const nextPast = history.past.concat([previousBlocks]);

    while (nextPast.length > HISTORY_LIMIT) {
        nextPast.shift();
    }

    return {
        past: nextPast,
        future: [],
        lastCoalesceKey: coalesceKey,
        lastCoalesceAt: now,
    };
}

export type EditorStore = StoreApi<EditorStoreState>;

const initialSelection: Selection = { clientId: null };

export function createEditorStore(initialBlocks: Block[] = []): EditorStore {
    return createStore<EditorStoreState>((set) => {
        function applyContentChange(
            updater: (state: EditorStoreState) => Partial<EditorStoreState> | null,
            coalesceKey: string | null
        ): void {
            set((state) => {
                const update = updater(state);

                if (update === null) {
                    return state;
                }

                const nextBlocks =
                    update.blocks !== undefined ? update.blocks : state.blocks;

                if (nextBlocks === state.blocks) {
                    return state;
                }

                const history = commitHistory(
                    state.history,
                    state.blocks,
                    coalesceKey,
                    Date.now()
                );

                return {
                    ...state,
                    ...update,
                    blocks: nextBlocks,
                    isDirty: true,
                    history,
                };
            });
        }

        return {
            blocks: initialBlocks,
            selection: initialSelection,
            isDirty: false,
            history: createInitialHistory(),

            insertBlock: (block: Block, location: InsertLocation = {}) => {
                applyContentChange((state) => {
                    const nextBlocks = insertIntoTree(
                        state.blocks,
                        block,
                        location.parentClientId ?? null,
                        location.index
                    );

                    return { blocks: nextBlocks };
                }, null);
            },

            updateBlockAttributes: (clientId: string, attrs: Record<string, unknown>) => {
                applyContentChange((state) => {
                    const nextBlocks = updateAttributesInTree(state.blocks, clientId, attrs);

                    return { blocks: nextBlocks };
                }, `updateBlockAttributes:${clientId}`);
            },

            removeBlock: (clientId: string) => {
                applyContentChange((state) => {
                    const nextBlocks = removeFromTree(state.blocks, clientId);

                    if (nextBlocks === state.blocks) {
                        return null;
                    }

                    const nextSelection = selectionExistsIn(nextBlocks, state.selection.clientId)
                        ? state.selection
                        : initialSelection;

                    return { blocks: nextBlocks, selection: nextSelection };
                }, null);
            },

            moveBlock: (clientId: string, location: MoveLocation) => {
                applyContentChange((state) => {
                    const parentClientId = location.parentClientId ?? null;

                    if (parentClientId !== null) {
                        return null;
                    }

                    const currentIndex = state.blocks.findIndex(
                        (block) => block.clientId === clientId
                    );

                    if (currentIndex === -1) {
                        return null;
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
                        return null;
                    }

                    return { blocks: withoutBlock };
                }, null);
            },

            replaceBlocks: (newBlocks: Block[]) => {
                applyContentChange(
                    () => ({ blocks: newBlocks, selection: initialSelection }),
                    null
                );
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

            undo: () => {
                set((state) => {
                    if (state.history.past.length === 0) {
                        return state;
                    }

                    const previousBlocks = state.history.past[state.history.past.length - 1];
                    const nextPast = state.history.past.slice(0, -1);
                    const nextFuture = [state.blocks, ...state.history.future];
                    const nextSelection = selectionExistsIn(
                        previousBlocks,
                        state.selection.clientId
                    )
                        ? state.selection
                        : initialSelection;

                    return {
                        ...state,
                        blocks: previousBlocks,
                        selection: nextSelection,
                        isDirty: true,
                        history: {
                            past: nextPast,
                            future: nextFuture,
                            lastCoalesceKey: null,
                            lastCoalesceAt: 0,
                        },
                    };
                });
            },

            redo: () => {
                set((state) => {
                    if (state.history.future.length === 0) {
                        return state;
                    }

                    const [nextBlocks, ...remainingFuture] = state.history.future;
                    const nextPast = state.history.past.concat([state.blocks]);

                    while (nextPast.length > HISTORY_LIMIT) {
                        nextPast.shift();
                    }

                    const nextSelection = selectionExistsIn(
                        nextBlocks,
                        state.selection.clientId
                    )
                        ? state.selection
                        : initialSelection;

                    return {
                        ...state,
                        blocks: nextBlocks,
                        selection: nextSelection,
                        isDirty: true,
                        history: {
                            past: nextPast,
                            future: remainingFuture,
                            lastCoalesceKey: null,
                            lastCoalesceAt: 0,
                        },
                    };
                });
            },
        };
    });
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

export function useCanUndo(store: EditorStore): boolean {
    return useStore(store, (state) => state.history.past.length > 0);
}

export function useCanRedo(store: EditorStore): boolean {
    return useStore(store, (state) => state.history.future.length > 0);
}
