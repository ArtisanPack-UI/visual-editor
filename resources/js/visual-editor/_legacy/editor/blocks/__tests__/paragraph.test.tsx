import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry, getBlock } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerParagraphBlock, PARAGRAPH_BLOCK_NAME } from '../paragraph';
import {
    clearBlockEditors,
    getBlockEditor,
} from '../shared/blockEditorRegistry';

function makeParagraph(clientId: string, content: string): Block {
    return {
        clientId,
        name: PARAGRAPH_BLOCK_NAME,
        attributes: { content },
        innerBlocks: [],
    };
}

function renderBlock(store: EditorStore, block: Block) {
    return render(
        <EditorStoreProvider store={store}>
            <RenderBlock block={block} />
        </EditorStoreProvider>
    );
}

beforeEach(() => {
    clearRegistry();
    clearBlockEditors();
    registerParagraphBlock();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('paragraph block registration', () => {
    it('registers ve/paragraph in the block registry', () => {
        expect(getBlock(PARAGRAPH_BLOCK_NAME)).toBeDefined();
    });
});

describe('paragraph block edit', () => {
    it('renders the initial content from attributes.content', () => {
        const block = makeParagraph('p1', '<p>Hello Tiptap</p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        expect(screen.getByText('Hello Tiptap')).toBeInTheDocument();
    });

    it('writes content back to the store when the editor updates', async () => {
        const block = makeParagraph('p1', '<p>initial</p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.insertContentAt(1, 'edited ');
        });

        const content = store.getState().blocks[0].attributes.content;
        expect(typeof content).toBe('string');
        expect(content as string).toContain('edited');
        expect(content as string).toContain('initial');
    });

    it('splits the paragraph into two blocks when Enter is pressed at the end', async () => {
        const block = makeParagraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.focus('end');
        });

        const tiptap = document.querySelector('.ve-richtext') as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(tiptap, { key: 'Enter' });
        });

        const blocks = store.getState().blocks;
        expect(blocks.length).toBe(2);
        expect(blocks[0].name).toBe(PARAGRAPH_BLOCK_NAME);
        expect(blocks[1].name).toBe(PARAGRAPH_BLOCK_NAME);
        expect(blocks[0].attributes.content).toContain('hello');
        expect(store.getState().selection.clientId).toBe(blocks[1].clientId);
    });

    it('splits mid-paragraph, moving text after the cursor into the new block', async () => {
        const block = makeParagraph('p1', '<p>hellothere</p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.setTextSelection(6);
        });

        const tiptap = document.querySelector('.ve-richtext') as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(tiptap, { key: 'Enter' });
        });

        const blocks = store.getState().blocks;
        expect(blocks.length).toBe(2);
        expect(blocks[0].attributes.content).toContain('hello');
        expect(blocks[0].attributes.content).not.toContain('there');
        expect(blocks[1].attributes.content).toContain('there');
    });

    it('produces a single undo entry for a split (Enter)', async () => {
        const block = makeParagraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');

        await act(async () => {
            editor!.commands.focus('end');
        });

        const tiptap = document.querySelector('.ve-richtext') as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(tiptap, { key: 'Enter' });
        });

        expect(store.getState().blocks.length).toBe(2);

        act(() => {
            store.getState().undo();
        });

        expect(store.getState().blocks.length).toBe(1);
        expect(store.getState().blocks[0].clientId).toBe('p1');
    });

    it('merges into the previous block when Backspace is pressed at the start', async () => {
        const first = makeParagraph('p1', '<p>first</p>');
        const second = makeParagraph('p2', '<p>second</p>');
        const store = createEditorStore([first, second]);

        renderBlock(store, first);
        renderBlock(store, second);

        const secondEditor = getBlockEditor('p2');
        expect(secondEditor).not.toBeNull();

        await act(async () => {
            secondEditor!.commands.focus('start');
        });

        const secondTiptap = secondEditor!.view.dom as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(secondTiptap, { key: 'Backspace' });
        });

        const blocks = store.getState().blocks;
        expect(blocks.length).toBe(1);
        expect(blocks[0].clientId).toBe('p1');
        const merged = blocks[0].attributes.content as string;
        expect(merged).toContain('first');
        expect(merged).toContain('second');
        expect(store.getState().selection.clientId).toBe('p1');
    });

    it('places the cursor at the merge boundary after backspace-merge', async () => {
        const first = makeParagraph('p1', '<p>first</p>');
        const second = makeParagraph('p2', '<p>second</p>');
        const store = createEditorStore([first, second]);

        renderBlock(store, first);
        renderBlock(store, second);

        const secondEditor = getBlockEditor('p2');

        await act(async () => {
            secondEditor!.commands.focus('start');
        });

        const secondTiptap = secondEditor!.view.dom as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(secondTiptap, { key: 'Backspace' });
        });

        const firstEditor = getBlockEditor('p1');
        expect(firstEditor).not.toBeNull();
        expect(firstEditor!.state.selection.from).toBe('first'.length + 1);
    });

    it('produces a single undo entry for a merge (Backspace)', async () => {
        const first = makeParagraph('p1', '<p>first</p>');
        const second = makeParagraph('p2', '<p>second</p>');
        const store = createEditorStore([first, second]);

        renderBlock(store, first);
        renderBlock(store, second);

        const secondEditor = getBlockEditor('p2');

        await act(async () => {
            secondEditor!.commands.focus('start');
        });

        const secondTiptap = secondEditor!.view.dom as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(secondTiptap, { key: 'Backspace' });
        });

        expect(store.getState().blocks.length).toBe(1);

        act(() => {
            store.getState().undo();
        });

        const restored = store.getState().blocks;
        expect(restored.length).toBe(2);
        expect(restored[0].clientId).toBe('p1');
        expect(restored[1].clientId).toBe('p2');
    });
});
