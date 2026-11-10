/**
 * Undo/redo history for the post editor's block tree.
 *
 * `BlockEditorProvider` reports two kinds of change: `onChange` for
 * persistent edits (inserting, splitting, moving or removing blocks,
 * toolbar and sidebar changes) and `onInput` for non-persistent ones,
 * mostly typing. Recording only `onChange` left typing out of the history,
 * so undoing a split or exit reverted everything typed since the previous
 * persistent edit, and the first edit after load couldn't be undone at all
 * (#1101 follow-up).
 *
 * This module keeps the history as plain data so the rules are testable
 * without mounting the editor:
 *
 *   - a run of typing becomes its own undo level, closed when a persistent
 *     change follows or when typing moves to another block or field;
 *   - undo first reverts uncommitted typing, then steps back through the
 *     recorded levels;
 *   - the tree the editor loaded is the baseline, so the first edit can be
 *     undone;
 *   - every entry carries the selection it was recorded with, so undo and
 *     redo can put the caret back.
 *
 * Entries hold the provider's block arrays by reference, and comparisons
 * are by identity, which is how the provider reports "no change".
 */

import type { BlockInstance } from '@wordpress/blocks';

/** One end of a block-editor selection, as the provider reports it. */
export interface HistorySelectionPoint {
    readonly clientId?: string;
    readonly attributeKey?: string;
    readonly offset?: number;
}

/** The selection `BlockEditorProvider` passes with `onChange` / `onInput`. */
export interface HistorySelection {
    readonly selectionStart?: HistorySelectionPoint;
    readonly selectionEnd?: HistorySelectionPoint;
    readonly initialPosition?: number | null;
}

/** A block tree with the selection it was recorded with. */
export interface HistoryEntry {
    readonly blocks: BlockInstance[];
    readonly selection?: HistorySelection;
}

/** The whole history: recorded levels plus the live state. */
export interface EditorHistory {
    /** Levels undo steps back through, oldest first. */
    readonly past: readonly HistoryEntry[];
    /** Levels redo steps forward through, the next one last. */
    readonly future: readonly HistoryEntry[];
    /** The last persistent state, or null before the first change. */
    readonly committed: HistoryEntry | null;
    /** Typing recorded since `committed`, or null when there is none. */
    readonly pending: HistoryEntry | null;
}

/** The result of an undo or redo: the next history and the entry to show. */
export interface HistoryStep {
    readonly history: EditorHistory;
    readonly entry: HistoryEntry;
}

export const EMPTY_EDITOR_HISTORY: EditorHistory = {
    past: [],
    future: [],
    committed: null,
    pending: null,
};

/**
 * Whether the selection points at a different block or field than the
 * other one, which ends a run of typing.
 */
function movedField(previous: HistoryEntry, next: HistoryEntry): boolean {
    const before = previous.selection?.selectionStart;
    const after = next.selection?.selectionStart;

    if (before?.clientId === undefined || after?.clientId === undefined) {
        return false;
    }

    return before.clientId !== after.clientId || before.attributeKey !== after.attributeKey;
}

/**
 * Whether typing is waiting to become an undo level.
 */
function hasPendingTyping(history: EditorHistory): boolean {
    return history.pending !== null && history.pending.blocks !== history.committed?.blocks;
}

/**
 * Records a non-persistent change (`onInput`).
 *
 * @param history  The current history.
 * @param entry    The tree and selection the provider reported.
 * @param baseline The tree the editor holds before any recorded change.
 *
 * @return The next history.
 */
export function recordInput(
    history: EditorHistory,
    entry: HistoryEntry,
    baseline: HistoryEntry
): EditorHistory {
    const committed = history.committed ?? baseline;

    // Typing that moved to another block or field closes the previous
    // run as its own level.
    if (history.pending !== null && hasPendingTyping(history) && movedField(history.pending, entry)) {
        return {
            past: [...history.past, committed],
            future: [],
            committed: history.pending,
            pending: entry,
        };
    }

    return {
        past: history.past,
        future: [],
        committed,
        pending: entry,
    };
}

/**
 * Records a persistent change (`onChange`).
 *
 * @param history  The current history.
 * @param entry    The tree and selection the provider reported.
 * @param baseline The tree the editor holds before any recorded change.
 *
 * @return The next history.
 */
export function recordChange(
    history: EditorHistory,
    entry: HistoryEntry,
    baseline: HistoryEntry
): EditorHistory {
    const committed = history.committed ?? baseline;
    const levels: HistoryEntry[] = [];

    if (committed.blocks !== entry.blocks) {
        levels.push(committed);
    }

    // The typing before this change becomes its own level, so undo
    // reverts the change first and the typing second. A change that
    // only marks the typed tree as persistent adds no extra level.
    if (
        history.pending !== null &&
        history.pending.blocks !== committed.blocks &&
        history.pending.blocks !== entry.blocks
    ) {
        levels.push(history.pending);
    }

    if (levels.length === 0) {
        return { ...history, committed: entry, pending: null };
    }

    return {
        past: [...history.past, ...levels],
        future: [],
        committed: entry,
        pending: null,
    };
}

/**
 * Whether undo has anything to do.
 */
export function canUndo(history: EditorHistory): boolean {
    return history.past.length > 0 || hasPendingTyping(history);
}

/**
 * Whether redo has anything to do.
 */
export function canRedo(history: EditorHistory): boolean {
    return history.future.length > 0;
}

/**
 * Steps back one level: uncommitted typing first, then the recorded
 * levels.
 *
 * @param history The current history.
 *
 * @return The step to apply, or null when there is nothing to undo.
 */
export function undo(history: EditorHistory): HistoryStep | null {
    if (hasPendingTyping(history) && history.pending !== null && history.committed !== null) {
        return {
            history: {
                past: history.past,
                future: [...history.future, history.pending],
                committed: history.committed,
                pending: null,
            },
            entry: history.committed,
        };
    }

    const previous = history.past[history.past.length - 1];

    if (previous === undefined || history.committed === null) {
        return null;
    }

    return {
        history: {
            past: history.past.slice(0, -1),
            future: [...history.future, history.committed],
            committed: previous,
            pending: null,
        },
        entry: previous,
    };
}

/**
 * Steps forward one level.
 *
 * @param history The current history.
 *
 * @return The step to apply, or null when there is nothing to redo.
 */
export function redo(history: EditorHistory): HistoryStep | null {
    const next = history.future[history.future.length - 1];

    if (next === undefined || history.committed === null) {
        return null;
    }

    return {
        history: {
            past: [...history.past, history.committed],
            future: history.future.slice(0, -1),
            committed: next,
            pending: null,
        },
        entry: next,
    };
}
