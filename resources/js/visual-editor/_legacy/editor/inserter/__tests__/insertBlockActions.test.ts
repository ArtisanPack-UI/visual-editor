import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createEditorStore, type Block } from '../../store';
import { clearRegistry } from '../../registry';
import { registerCoreBlocks, PARAGRAPH_BLOCK_NAME, HEADING_BLOCK_NAME } from '../../blocks';
import {
    clearInserterRegistry,
    insertBlockAtSelection,
    replaceBlockWithInserterBlock,
} from '../index';

function makeParagraph(clientId: string, content: string): Block {
    return {
        clientId,
        name: PARAGRAPH_BLOCK_NAME,
        attributes: { content },
        innerBlocks: [],
    };
}

beforeEach(() => {
    clearRegistry();
    clearInserterRegistry();
    registerCoreBlocks();
});

afterEach(() => {
    clearRegistry();
    clearInserterRegistry();
});

describe('insertBlockAtSelection', () => {
    it('appends a block when nothing is selected', () => {
        const store = createEditorStore([makeParagraph('p1', '<p>a</p>')]);

        const inserted = insertBlockAtSelection(store, HEADING_BLOCK_NAME);

        expect(inserted).not.toBeNull();
        expect(store.getState().blocks).toHaveLength(2);
        expect(store.getState().blocks[1].name).toBe(HEADING_BLOCK_NAME);
    });

    it('inserts after the selected block when the selection edge is end', () => {
        const store = createEditorStore([
            makeParagraph('p1', '<p>a</p>'),
            makeParagraph('p2', '<p>b</p>'),
        ]);
        store.getState().select('p1', 'end');

        insertBlockAtSelection(store, HEADING_BLOCK_NAME);

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(3);
        expect(blocks[0].clientId).toBe('p1');
        expect(blocks[1].name).toBe(HEADING_BLOCK_NAME);
        expect(blocks[2].clientId).toBe('p2');
    });

    it('inserts before the selected block when the selection edge is start', () => {
        const store = createEditorStore([
            makeParagraph('p1', '<p>a</p>'),
            makeParagraph('p2', '<p>b</p>'),
        ]);
        store.getState().select('p2', 'start');

        insertBlockAtSelection(store, HEADING_BLOCK_NAME);

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(3);
        expect(blocks[0].clientId).toBe('p1');
        expect(blocks[1].name).toBe(HEADING_BLOCK_NAME);
        expect(blocks[2].clientId).toBe('p2');
    });

    it('selects the newly inserted block', () => {
        const store = createEditorStore([makeParagraph('p1', '<p>a</p>')]);

        const inserted = insertBlockAtSelection(store, PARAGRAPH_BLOCK_NAME);

        expect(store.getState().selection.clientId).toBe(inserted!.clientId);
    });

    it('returns null when no factory is registered for the block', () => {
        const store = createEditorStore([makeParagraph('p1', '<p>a</p>')]);

        const inserted = insertBlockAtSelection(store, 'unknown/block');

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

        const replacement = replaceBlockWithInserterBlock(store, 'p2', HEADING_BLOCK_NAME);

        expect(replacement).not.toBeNull();
        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[1].name).toBe(HEADING_BLOCK_NAME);
        expect(blocks[1].clientId).toBe(replacement!.clientId);
        expect(store.getState().selection.clientId).toBe(replacement!.clientId);
    });

    it('commits the replacement as a single undo entry', () => {
        const store = createEditorStore([
            makeParagraph('p1', '<p>first</p>'),
            makeParagraph('p2', '<p>/head</p>'),
        ]);

        replaceBlockWithInserterBlock(store, 'p2', HEADING_BLOCK_NAME);

        store.getState().undo();

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[1].clientId).toBe('p2');
        expect(blocks[1].name).toBe(PARAGRAPH_BLOCK_NAME);
    });
});
