import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createEditorStore } from '../editorStore';
import type { Block } from '../types';

function makeBlock(clientId: string, name = 've/paragraph', overrides: Partial<Block> = {}): Block {
    return {
        clientId,
        name,
        attributes: {},
        innerBlocks: [],
        ...overrides,
    };
}

describe('editor store history', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T00:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('initial state', () => {
        it('starts with empty history', () => {
            const store = createEditorStore();
            const { history } = store.getState();

            expect(history.past).toEqual([]);
            expect(history.future).toEqual([]);
        });

        it('accepting initial blocks does not create a history entry', () => {
            const store = createEditorStore([makeBlock('seed')]);
            expect(store.getState().history.past).toHaveLength(0);
        });
    });

    describe('undo / redo round trip', () => {
        it('undoes an insert and restores the previous tree', () => {
            const store = createEditorStore();
            const first = makeBlock('p-1');

            store.getState().insertBlock(first);
            expect(store.getState().blocks).toEqual([first]);

            store.getState().undo();
            expect(store.getState().blocks).toEqual([]);
            expect(store.getState().history.past).toHaveLength(0);
            expect(store.getState().history.future).toHaveLength(1);
        });

        it('redoes an undone insert', () => {
            const store = createEditorStore();
            const first = makeBlock('p-1');

            store.getState().insertBlock(first);
            store.getState().undo();
            store.getState().redo();

            expect(store.getState().blocks).toEqual([first]);
            expect(store.getState().history.past).toHaveLength(1);
            expect(store.getState().history.future).toHaveLength(0);
        });

        it('round-trips a sequence of mutations', () => {
            const store = createEditorStore();

            store.getState().insertBlock(makeBlock('p-1'));
            store.getState().insertBlock(makeBlock('p-2'));
            vi.advanceTimersByTime(1000);
            store.getState().updateBlockAttributes('p-1', { content: 'hello' });
            vi.advanceTimersByTime(1000);
            store.getState().removeBlock('p-2');

            expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['p-1']);

            store.getState().undo();
            expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['p-1', 'p-2']);

            store.getState().undo();
            expect(store.getState().blocks[0].attributes.content).toBeUndefined();

            store.getState().undo();
            expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['p-1']);

            store.getState().undo();
            expect(store.getState().blocks).toEqual([]);

            store.getState().redo();
            store.getState().redo();
            store.getState().redo();
            store.getState().redo();
            expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['p-1']);
            expect(store.getState().blocks[0].attributes.content).toBe('hello');
        });

        it('undo past the bottom of the stack is a no-op', () => {
            const store = createEditorStore();
            const stateBefore = store.getState();

            store.getState().undo();
            const stateAfter = store.getState();

            expect(stateAfter.blocks).toBe(stateBefore.blocks);
            expect(stateAfter.history).toBe(stateBefore.history);
        });

        it('redo past the top of the stack is a no-op', () => {
            const store = createEditorStore();
            store.getState().insertBlock(makeBlock('p-1'));
            const stateBefore = store.getState();

            store.getState().redo();
            const stateAfter = store.getState();

            expect(stateAfter.blocks).toBe(stateBefore.blocks);
            expect(stateAfter.history).toBe(stateBefore.history);
        });

        it('a new mutation after undo clears the redo stack', () => {
            const store = createEditorStore();
            store.getState().insertBlock(makeBlock('p-1'));
            store.getState().undo();

            expect(store.getState().history.future).toHaveLength(1);

            store.getState().insertBlock(makeBlock('p-2'));
            expect(store.getState().history.future).toHaveLength(0);
        });
    });

    describe('selection changes', () => {
        it('does not create history entries for select', () => {
            const store = createEditorStore([makeBlock('p-1')]);

            store.getState().select('p-1');
            store.getState().select('p-1', 'end');
            store.getState().clearSelection();

            expect(store.getState().history.past).toHaveLength(0);
        });

        it('does not break history round-trips when selection changes between mutations', () => {
            const store = createEditorStore();
            store.getState().insertBlock(makeBlock('p-1'));
            store.getState().select('p-1');
            vi.advanceTimersByTime(1000);
            store.getState().insertBlock(makeBlock('p-2'));

            store.getState().undo();
            expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['p-1']);

            store.getState().undo();
            expect(store.getState().blocks).toEqual([]);
        });
    });

    describe('coalescing rapid attribute updates', () => {
        it('coalesces rapid updates to the same block into a single entry', () => {
            const store = createEditorStore([
                makeBlock('p-1', 've/paragraph', { attributes: { content: '' } }),
            ]);

            store.getState().updateBlockAttributes('p-1', { content: 'h' });
            vi.advanceTimersByTime(50);
            store.getState().updateBlockAttributes('p-1', { content: 'he' });
            vi.advanceTimersByTime(50);
            store.getState().updateBlockAttributes('p-1', { content: 'hel' });
            vi.advanceTimersByTime(50);
            store.getState().updateBlockAttributes('p-1', { content: 'hell' });
            vi.advanceTimersByTime(50);
            store.getState().updateBlockAttributes('p-1', { content: 'hello' });

            expect(store.getState().history.past).toHaveLength(1);

            store.getState().undo();
            expect(store.getState().blocks[0].attributes.content).toBe('');
        });

        it('starts a new history entry once the coalesce window has elapsed', () => {
            const store = createEditorStore([
                makeBlock('p-1', 've/paragraph', { attributes: { content: '' } }),
            ]);

            store.getState().updateBlockAttributes('p-1', { content: 'burst one' });
            vi.advanceTimersByTime(1000);
            store.getState().updateBlockAttributes('p-1', { content: 'burst two' });

            expect(store.getState().history.past).toHaveLength(2);

            store.getState().undo();
            expect(store.getState().blocks[0].attributes.content).toBe('burst one');

            store.getState().undo();
            expect(store.getState().blocks[0].attributes.content).toBe('');
        });

        it('does not coalesce updates to different blocks', () => {
            const store = createEditorStore([
                makeBlock('p-1', 've/paragraph', { attributes: { content: '' } }),
                makeBlock('p-2', 've/paragraph', { attributes: { content: '' } }),
            ]);

            store.getState().updateBlockAttributes('p-1', { content: 'one' });
            vi.advanceTimersByTime(50);
            store.getState().updateBlockAttributes('p-2', { content: 'two' });

            expect(store.getState().history.past).toHaveLength(2);
        });

        it('does not coalesce across non-update actions', () => {
            const store = createEditorStore([
                makeBlock('p-1', 've/paragraph', { attributes: { content: '' } }),
            ]);

            store.getState().updateBlockAttributes('p-1', { content: 'first' });
            store.getState().insertBlock(makeBlock('p-2'));
            store.getState().updateBlockAttributes('p-1', { content: 'second' });

            expect(store.getState().history.past).toHaveLength(3);
        });
    });

    describe('bounded stack', () => {
        it('drops the oldest entry when history exceeds 100 entries', () => {
            const store = createEditorStore();

            for (let i = 0; i < 105; i++) {
                vi.advanceTimersByTime(1000);
                store.getState().insertBlock(makeBlock(`p-${i}`));
            }

            expect(store.getState().history.past).toHaveLength(100);

            // Undoing 100 times should walk back to the state after the first
            // 5 inserts (which were dropped from the past), leaving p-0..p-4.
            for (let i = 0; i < 100; i++) {
                store.getState().undo();
            }

            expect(store.getState().blocks.map((b) => b.clientId)).toEqual([
                'p-0',
                'p-1',
                'p-2',
                'p-3',
                'p-4',
            ]);
        });
    });

    describe('no-op actions', () => {
        it('no-op insert does not create a history entry', () => {
            const store = createEditorStore();

            store.getState().insertBlock(makeBlock('p-1'), { parentClientId: 'missing' });
            expect(store.getState().history.past).toHaveLength(0);
        });

        it('removing a non-existent block does not create a history entry', () => {
            const store = createEditorStore([makeBlock('p-1')]);

            store.getState().removeBlock('does-not-exist');
            expect(store.getState().history.past).toHaveLength(0);
        });

        it('moveBlock to the same index does not create a history entry', () => {
            const store = createEditorStore([makeBlock('p-1'), makeBlock('p-2')]);

            store.getState().moveBlock('p-1', { index: 0 });
            expect(store.getState().history.past).toHaveLength(0);
        });
    });

    describe('dirty flag', () => {
        it('content mutations mark the store dirty and populate history', () => {
            const store = createEditorStore();

            store.getState().insertBlock(makeBlock('p-1'));
            expect(store.getState().isDirty).toBe(true);
            expect(store.getState().history.past).toHaveLength(1);
        });
    });
});
