/**
 * Tests for the post editor's undo/redo history.
 */

import { describe, expect, it } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

import {
    canRedo,
    canUndo,
    EMPTY_EDITOR_HISTORY,
    recordChange,
    recordInput,
    redo,
    undo,
    type EditorHistory,
    type HistoryEntry,
    type HistorySelection,
} from '../editor-history';

/** A distinct block tree; the history only compares references. */
function tree(label: string): BlockInstance[] {
    return [{ clientId: label, name: 'test/paragraph', isValid: true, attributes: {}, innerBlocks: [] } as BlockInstance];
}

function caret(clientId: string, offset: number, attributeKey = 'content'): HistorySelection {
    return {
        selectionStart: { clientId, attributeKey, offset },
        selectionEnd: { clientId, attributeKey, offset },
    };
}

function entry(blocks: BlockInstance[], selection?: HistorySelection): HistoryEntry {
    return { blocks, selection };
}

const loaded = tree('loaded');
const baseline = entry(loaded);

describe('editor history', () => {
    it('starts with nothing to undo or redo', () => {
        expect(canUndo(EMPTY_EDITOR_HISTORY)).toBe(false);
        expect(canRedo(EMPTY_EDITOR_HISTORY)).toBe(false);
        expect(undo(EMPTY_EDITOR_HISTORY)).toBeNull();
        expect(redo(EMPTY_EDITOR_HISTORY)).toBeNull();
    });

    it('undoes the first change back to the loaded tree', () => {
        const inserted = tree('inserted');
        const history = recordChange(EMPTY_EDITOR_HISTORY, entry(inserted), baseline);

        expect(canUndo(history)).toBe(true);

        const step = undo(history);

        expect(step?.entry.blocks).toBe(loaded);
        expect(canUndo(step!.history)).toBe(false);
        expect(canRedo(step!.history)).toBe(true);
    });

    it('undoes typing that no persistent change has followed', () => {
        const typed = tree('typed');
        const history = recordInput(EMPTY_EDITOR_HISTORY, entry(typed, caret('p', 3)), baseline);

        expect(canUndo(history)).toBe(true);

        const step = undo(history);

        expect(step?.entry.blocks).toBe(loaded);

        // Redo brings the typing back.
        const redone = redo(step!.history);

        expect(redone?.entry.blocks).toBe(typed);
        expect(redone?.entry.selection).toEqual(caret('p', 3));
    });

    it('undoes a split separately from the typing before it', () => {
        const firstKey = tree('a');
        const typed = tree('abc def');
        const split = tree('abc def + paragraph');

        let history: EditorHistory = recordChange(EMPTY_EDITOR_HISTORY, entry(firstKey, caret('p', 1)), baseline);
        history = recordInput(history, entry(tree('ab'), caret('p', 2)), baseline);
        history = recordInput(history, entry(typed, caret('p', 7)), baseline);
        history = recordChange(history, entry(split, caret('q', 0)), baseline);

        const undoSplit = undo(history);

        expect(undoSplit?.entry.blocks).toBe(typed);
        expect(undoSplit?.entry.selection).toEqual(caret('p', 7));

        const undoTyping = undo(undoSplit!.history);

        expect(undoTyping?.entry.blocks).toBe(firstKey);

        const undoFirstKey = undo(undoTyping!.history);

        expect(undoFirstKey?.entry.blocks).toBe(loaded);
        expect(undo(undoFirstKey!.history)).toBeNull();

        // And all the way forward again.
        let forward = undoFirstKey!.history;
        const seen: BlockInstance[][] = [];

        while (canRedo(forward)) {
            const step = redo(forward)!;

            seen.push(step.entry.blocks);
            forward = step.history;
        }

        expect(seen).toEqual([firstKey, typed, split]);
    });

    it('adds no extra level when a change only makes the typed tree persistent', () => {
        const typed = tree('typed');
        let history = recordInput(EMPTY_EDITOR_HISTORY, entry(typed), baseline);

        history = recordChange(history, entry(typed), baseline);

        expect(history.past).toHaveLength(1);
        expect(undo(history)?.entry.blocks).toBe(loaded);
    });

    it('adds no level for a change that reports the committed tree again', () => {
        const inserted = tree('inserted');
        let history = recordChange(EMPTY_EDITOR_HISTORY, entry(inserted), baseline);

        history = recordChange(history, entry(inserted), baseline);

        expect(history.past).toHaveLength(1);
    });

    it('starts a new level when typing moves to another block', () => {
        const inP = tree('typed in p');
        const inQ = tree('typed in q');
        const start = tree('start');
        let history = recordChange(EMPTY_EDITOR_HISTORY, entry(start, caret('p', 0)), baseline);

        history = recordInput(history, entry(inP, caret('p', 5)), baseline);
        history = recordInput(history, entry(inQ, caret('q', 5)), baseline);

        const first = undo(history);

        expect(first?.entry.blocks).toBe(inP);
        expect(undo(first!.history)?.entry.blocks).toBe(start);
    });

    it('starts a new level when typing moves to another field of the same block', () => {
        const inValue = tree('value');
        const inCitation = tree('citation');
        let history = recordChange(EMPTY_EDITOR_HISTORY, entry(tree('start'), caret('q', 0, 'value')), baseline);

        history = recordInput(history, entry(inValue, caret('q', 3, 'value')), baseline);
        history = recordInput(history, entry(inCitation, caret('q', 2, 'citation')), baseline);

        expect(undo(history)?.entry.blocks).toBe(inValue);
    });

    it('clears redo when a new edit follows an undo', () => {
        let history = recordChange(EMPTY_EDITOR_HISTORY, entry(tree('one')), baseline);

        history = undo(history)!.history;
        expect(canRedo(history)).toBe(true);

        expect(canRedo(recordInput(history, entry(tree('typed')), baseline))).toBe(false);
        expect(canRedo(recordChange(history, entry(tree('two')), baseline))).toBe(false);
    });

    it('records each entry with its selection so undo restores the caret', () => {
        const typed = tree('typed');
        let history = recordChange(EMPTY_EDITOR_HISTORY, entry(tree('start'), caret('p', 0)), baseline);

        history = recordInput(history, entry(typed, caret('p', 5)), baseline);
        history = recordChange(history, entry(tree('split'), caret('next', 0)), baseline);

        expect(undo(history)?.entry.selection).toEqual(caret('p', 5));
    });
});
