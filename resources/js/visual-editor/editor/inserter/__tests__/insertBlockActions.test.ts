import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createEditorStore, type Block } from '../../store';
import {
    clearInserterRegistry,
    insertBlockAtSelection,
    registerBuiltinInserterBlocks,
    replaceBlockWithInserterBlock,
} from '../index';

function makeParagraph(clientId: string, content: string): Block {
    return {
        clientId,
        name: 've/paragraph',
        attributes: { content },
        innerBlocks: [],
    };
}

beforeEach(() => {
    clearInserterRegistry();
    registerBuiltinInserterBlocks();
});

afterEach(() => {
    clearInserterRegistry();
});

describe('insertBlockAtSelection', () => {
    it('appends a block when nothing is selected', () => {
        const store = createEditorStore([makeParagraph('p1', '<p>a</p>')]);

        const inserted = insertBlockAtSelection(store, 've/heading');

        expect(inserted).not.toBeNull();
        expect(store.getState().blocks).toHaveLength(2);
        expect(store.getState().blocks[1].name).toBe('ve/heading');
    });

    it('inserts after the selected block', () => {
        const store = createEditorStore([
            makeParagraph('p1', '<p>a</p>'),
            makeParagraph('p2', '<p>b</p>'),
        ]);
        store.getState().select('p1');

        insertBlockAtSelection(store, 've/heading');

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(3);
        expect(blocks[0].clientId).toBe('p1');
        expect(blocks[1].name).toBe('ve/heading');
        expect(blocks[2].clientId).toBe('p2');
    });

    it('selects the newly inserted block', () => {
        const store = createEditorStore([makeParagraph('p1', '<p>a</p>')]);

        const inserted = insertBlockAtSelection(store, 've/paragraph');

        expect(store.getState().selection.clientId).toBe(inserted!.clientId);
    });

    it('returns null when no factory is registered for the block', () => {
        const store = createEditorStore([makeParagraph('p1', '<p>a</p>')]);

        const inserted = insertBlockAtSelection(store, 've/unknown');

        expect(inserted).toBeNull();
        expect(store.getState().blocks).toHaveLength(1);
    });
});

describe('replaceBlockWithInserterBlock', () => {
    it('replaces the current block with a new one of the given type', () => {
        const store = createEditorStore([
            makeParagraph('p1', '<p>first</p>'),
            makeParagraph('p2', '<p>/head</p>'),
        ]);

        const replacement = replaceBlockWithInserterBlock(store, 'p2', 've/heading');

        expect(replacement).not.toBeNull();
        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[1].name).toBe('ve/heading');
        expect(blocks[1].clientId).toBe(replacement!.clientId);
        expect(store.getState().selection.clientId).toBe(replacement!.clientId);
    });

    it('commits the replacement as a single undo entry', () => {
        const store = createEditorStore([
            makeParagraph('p1', '<p>first</p>'),
            makeParagraph('p2', '<p>/head</p>'),
        ]);

        replaceBlockWithInserterBlock(store, 'p2', 've/heading');

        store.getState().undo();

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[1].clientId).toBe('p2');
        expect(blocks[1].name).toBe('ve/paragraph');
    });
});
