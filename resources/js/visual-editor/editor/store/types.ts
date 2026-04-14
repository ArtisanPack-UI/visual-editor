export type Block = {
    clientId: string;
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: Block[];
};

export type SelectionEdge = 'start' | 'end';

export type Selection = {
    clientId: string | null;
    edge?: SelectionEdge;
};

export type InsertLocation = {
    parentClientId?: string | null;
    index?: number;
};

export type MoveLocation = {
    parentClientId?: string | null;
    index: number;
};

export type EditorState = {
    blocks: Block[];
    selection: Selection;
    isDirty: boolean;
};

export type HistorySnapshot = {
    blocks: Block[];
    selection: Selection;
};

export type HistoryState = {
    past: HistorySnapshot[];
    future: HistorySnapshot[];
    lastCoalesceKey: string | null;
    lastCoalesceAt: number;
};

export type EditorActions = {
    insertBlock: (block: Block, location?: InsertLocation) => void;
    updateBlockAttributes: (clientId: string, attrs: Record<string, unknown>) => void;
    removeBlock: (clientId: string) => void;
    moveBlock: (clientId: string, location: MoveLocation) => void;
    replaceBlocks: (newBlocks: Block[]) => void;
    select: (clientId: string, edge?: SelectionEdge) => void;
    clearSelection: () => void;
    markDirty: () => void;
    markClean: () => void;
    undo: () => void;
    redo: () => void;
};

export type EditorStoreState = EditorState &
    EditorActions & {
        history: HistoryState;
    };
