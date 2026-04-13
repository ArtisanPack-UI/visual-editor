import { describe, expect, it } from 'vitest';
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

function nestedTree(): Block[] {
    return [
        makeBlock('ql-1', 've/query-loop', {
            attributes: { postType: 'post', perPage: 5 },
            innerBlocks: [
                makeBlock('title-1', 've/post-title', { attributes: { level: 2 } }),
                makeBlock('para-1', 've/paragraph', {
                    attributes: { content: '<p>hello</p>' },
                }),
            ],
        }),
        makeBlock('para-top', 've/paragraph', {
            attributes: { content: '<p>top-level</p>' },
        }),
    ];
}

describe('createEditorStore', () => {
    it('initialises with defaults', () => {
        const store = createEditorStore();
        const state = store.getState();

        expect(state.blocks).toEqual([]);
        expect(state.selection).toEqual({ clientId: null });
        expect(state.isDirty).toBe(false);
    });

    it('accepts an initial block tree', () => {
        const tree = nestedTree();
        const store = createEditorStore(tree);

        expect(store.getState().blocks).toBe(tree);
        expect(store.getState().isDirty).toBe(false);
    });
});

describe('insertBlock', () => {
    it('appends to the top level by default', () => {
        const store = createEditorStore();

        store.getState().insertBlock(makeBlock('p-1'));

        const { blocks, isDirty } = store.getState();

        expect(blocks).toHaveLength(1);
        expect(blocks[0].clientId).toBe('p-1');
        expect(isDirty).toBe(true);
    });

    it('inserts at an explicit top-level index', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('c')]);

        store.getState().insertBlock(makeBlock('b'), { index: 1 });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a', 'b', 'c']);
    });

    it('clamps an out-of-range index to the end', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().insertBlock(makeBlock('b'), { index: 99 });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a', 'b']);
    });

    it('inserts into a nested parent', () => {
        const store = createEditorStore(nestedTree());

        store.getState().insertBlock(makeBlock('new-child'), {
            parentClientId: 'ql-1',
            index: 1,
        });

        const queryLoop = store.getState().blocks[0];

        expect(queryLoop.innerBlocks.map((b) => b.clientId)).toEqual([
            'title-1',
            'new-child',
            'para-1',
        ]);
    });

    it('produces a new blocks reference (immutable update)', () => {
        const original = [makeBlock('a')];
        const store = createEditorStore(original);

        store.getState().insertBlock(makeBlock('b'));

        const next = store.getState().blocks;

        expect(next).not.toBe(original);
        expect(original).toHaveLength(1);
    });

    it('is a no-op for an unknown parentClientId', () => {
        const store = createEditorStore(nestedTree());
        const beforeBlocks = store.getState().blocks;

        store.getState().markClean();
        store.getState().insertBlock(makeBlock('orphan'), { parentClientId: 'missing' });

        expect(store.getState().blocks).toBe(beforeBlocks);
        expect(store.getState().isDirty).toBe(false);
    });
});

describe('updateBlockAttributes', () => {
    it('merges attributes on a top-level block', () => {
        const store = createEditorStore([
            makeBlock('p-1', 've/paragraph', { attributes: { content: 'hi', dropCap: false } }),
        ]);

        store.getState().updateBlockAttributes('p-1', { content: 'updated' });

        expect(store.getState().blocks[0].attributes).toEqual({
            content: 'updated',
            dropCap: false,
        });
        expect(store.getState().isDirty).toBe(true);
    });

    it('updates a nested block', () => {
        const store = createEditorStore(nestedTree());

        store.getState().updateBlockAttributes('para-1', { content: '<p>changed</p>' });

        const queryLoop = store.getState().blocks[0];
        const para = queryLoop.innerBlocks.find((b) => b.clientId === 'para-1');

        expect(para?.attributes.content).toBe('<p>changed</p>');
    });

    it('produces a new blocks reference and parent reference when nested', () => {
        const original = nestedTree();
        const store = createEditorStore(original);

        store.getState().updateBlockAttributes('para-1', { content: 'x' });

        const next = store.getState().blocks;

        expect(next).not.toBe(original);
        expect(next[0]).not.toBe(original[0]);
        expect(next[1]).toBe(original[1]);
    });

    it('is a no-op for a missing clientId', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().markClean();
        store.getState().updateBlockAttributes('missing', { x: 1 });

        expect(store.getState().isDirty).toBe(false);
    });
});

describe('removeBlock', () => {
    it('removes a top-level block', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('b')]);

        store.getState().removeBlock('a');

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['b']);
        expect(store.getState().isDirty).toBe(true);
    });

    it('removes a nested block', () => {
        const store = createEditorStore(nestedTree());

        store.getState().removeBlock('title-1');

        const queryLoop = store.getState().blocks[0];

        expect(queryLoop.innerBlocks.map((b) => b.clientId)).toEqual(['para-1']);
    });

    it('clears the selection if the removed block was selected', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().select('a', 'end');
        store.getState().removeBlock('a');

        expect(store.getState().selection).toEqual({ clientId: null });
    });

    it('leaves selection alone if another block was selected', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('b')]);

        store.getState().select('b');
        store.getState().removeBlock('a');

        expect(store.getState().selection.clientId).toBe('b');
    });

    it('is a no-op for a missing clientId', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().markClean();
        store.getState().removeBlock('missing');

        expect(store.getState().isDirty).toBe(false);
    });

    it('clears the selection when a removed ancestor contained the selected block', () => {
        const store = createEditorStore(nestedTree());

        store.getState().select('para-1', 'end');
        store.getState().removeBlock('ql-1');

        expect(store.getState().selection).toEqual({ clientId: null });
    });

    it('preserves the selection when the selected block is in an untouched subtree', () => {
        const store = createEditorStore(nestedTree());

        store.getState().select('para-top');
        store.getState().removeBlock('ql-1');

        expect(store.getState().selection.clientId).toBe('para-top');
    });
});

describe('moveBlock', () => {
    it('moves a top-level block forward', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('b'), makeBlock('c')]);

        store.getState().moveBlock('a', { index: 2 });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['b', 'c', 'a']);
        expect(store.getState().isDirty).toBe(true);
    });

    it('moves a top-level block backward', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('b'), makeBlock('c')]);

        store.getState().moveBlock('c', { index: 0 });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['c', 'a', 'b']);
    });

    it('clamps an out-of-range index', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('b')]);

        store.getState().moveBlock('a', { index: 99 });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['b', 'a']);
    });

    it('is a no-op when a nested parentClientId is requested (Phase 1 scope)', () => {
        const store = createEditorStore(nestedTree());

        store.getState().markClean();
        store.getState().moveBlock('title-1', { parentClientId: 'ql-1', index: 1 });

        expect(store.getState().isDirty).toBe(false);
        const queryLoop = store.getState().blocks[0];
        expect(queryLoop.innerBlocks.map((b) => b.clientId)).toEqual(['title-1', 'para-1']);
    });

    it('is a no-op for a missing clientId', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().markClean();
        store.getState().moveBlock('missing', { index: 0 });

        expect(store.getState().isDirty).toBe(false);
    });
});

describe('replaceBlocks', () => {
    it('replaces the block tree and resets selection', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().select('a');
        store.getState().replaceBlocks([makeBlock('x'), makeBlock('y')]);

        const state = store.getState();

        expect(state.blocks.map((b) => b.clientId)).toEqual(['x', 'y']);
        expect(state.selection).toEqual({ clientId: null });
        expect(state.isDirty).toBe(true);
    });

    it('accepts an empty array', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().replaceBlocks([]);

        expect(store.getState().blocks).toEqual([]);
    });
});

describe('selection', () => {
    it('select() stores the clientId and edge', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().select('a', 'end');

        expect(store.getState().selection).toEqual({ clientId: 'a', edge: 'end' });
    });

    it('select() without an edge leaves it undefined', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().select('a');

        expect(store.getState().selection.clientId).toBe('a');
        expect(store.getState().selection.edge).toBeUndefined();
    });

    it('clearSelection() resets the selection', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().select('a', 'start');
        store.getState().clearSelection();

        expect(store.getState().selection).toEqual({ clientId: null });
    });

    it('selection changes do not flip the dirty flag', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().select('a');
        expect(store.getState().isDirty).toBe(false);

        store.getState().clearSelection();
        expect(store.getState().isDirty).toBe(false);
    });
});

describe('dirty flag', () => {
    it('starts clean', () => {
        expect(createEditorStore().getState().isDirty).toBe(false);
    });

    it('flips on insertBlock', () => {
        const store = createEditorStore();

        store.getState().insertBlock(makeBlock('a'));

        expect(store.getState().isDirty).toBe(true);
    });

    it('flips on updateBlockAttributes', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().updateBlockAttributes('a', { x: 1 });

        expect(store.getState().isDirty).toBe(true);
    });

    it('flips on removeBlock', () => {
        const store = createEditorStore([makeBlock('a')]);

        store.getState().removeBlock('a');

        expect(store.getState().isDirty).toBe(true);
    });

    it('flips on moveBlock', () => {
        const store = createEditorStore([makeBlock('a'), makeBlock('b')]);

        store.getState().moveBlock('a', { index: 1 });

        expect(store.getState().isDirty).toBe(true);
    });

    it('flips on replaceBlocks', () => {
        const store = createEditorStore();

        store.getState().replaceBlocks([makeBlock('a')]);

        expect(store.getState().isDirty).toBe(true);
    });

    it('markClean() only clears via explicit call', () => {
        const store = createEditorStore();

        store.getState().insertBlock(makeBlock('a'));
        expect(store.getState().isDirty).toBe(true);

        store.getState().markClean();
        expect(store.getState().isDirty).toBe(false);

        store.getState().updateBlockAttributes('a', { x: 1 });
        expect(store.getState().isDirty).toBe(true);
    });

    it('markDirty() explicitly flips the flag', () => {
        const store = createEditorStore();

        store.getState().markDirty();

        expect(store.getState().isDirty).toBe(true);
    });
});

describe('round-trip nested tree', () => {
    it('preserves structure through insert → update → remove', () => {
        const store = createEditorStore(nestedTree());

        store.getState().insertBlock(
            makeBlock('footnote', 've/paragraph', {
                attributes: { content: '<p>footnote</p>' },
            }),
            { parentClientId: 'ql-1' }
        );

        store.getState().updateBlockAttributes('footnote', { content: '<p>edited</p>' });

        const ql = store.getState().blocks[0];
        const footnote = ql.innerBlocks.find((b) => b.clientId === 'footnote');

        expect(footnote?.attributes.content).toBe('<p>edited</p>');
        expect(ql.innerBlocks.map((b) => b.clientId)).toEqual(['title-1', 'para-1', 'footnote']);

        store.getState().removeBlock('footnote');

        const qlAfter = store.getState().blocks[0];
        expect(qlAfter.innerBlocks.map((b) => b.clientId)).toEqual(['title-1', 'para-1']);
    });
});
