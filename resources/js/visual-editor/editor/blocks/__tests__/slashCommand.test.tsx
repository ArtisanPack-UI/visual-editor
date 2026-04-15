import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerCoreBlocks, PARAGRAPH_BLOCK_NAME } from '../index';
import {
    clearBlockEditors,
    getBlockEditor,
} from '../shared/blockEditorRegistry';
import {
    clearInserterRegistry,
    registerBuiltinInserterBlocks,
} from '../../inserter';

function paragraph(clientId: string, content: string): Block {
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
    clearInserterRegistry();
    registerCoreBlocks();
    registerBuiltinInserterBlocks();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
    clearInserterRegistry();
});

describe('slash command', () => {
    it('opens the popover when the paragraph content becomes a slash query', async () => {
        const block = paragraph('p1', '<p></p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.insertContent('/');
        });

        expect(screen.getByTestId('ve-slash-command-popover')).toBeInTheDocument();
        expect(screen.getByTestId('ve-slash-command-item-ve/paragraph')).toBeInTheDocument();
        expect(screen.getByTestId('ve-slash-command-item-ve/heading')).toBeInTheDocument();
    });

    it('filters the popover by the text after the slash', async () => {
        const block = paragraph('p1', '<p></p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.insertContent('/head');
        });

        expect(screen.queryByTestId('ve-slash-command-item-ve/paragraph')).toBeNull();
        expect(screen.getByTestId('ve-slash-command-item-ve/heading')).toBeInTheDocument();
    });

    it('moves the selected index with ArrowDown / ArrowUp', async () => {
        const block = paragraph('p1', '<p></p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.insertContent('/');
        });

        expect(
            screen
                .getByTestId('ve-slash-command-item-ve/paragraph')
                .getAttribute('data-selected')
        ).toBe('true');

        await act(async () => {
            fireEvent.keyDown(window, { key: 'ArrowDown' });
        });

        expect(
            screen
                .getByTestId('ve-slash-command-item-ve/heading')
                .getAttribute('data-selected')
        ).toBe('true');

        await act(async () => {
            fireEvent.keyDown(window, { key: 'ArrowUp' });
        });

        expect(
            screen
                .getByTestId('ve-slash-command-item-ve/paragraph')
                .getAttribute('data-selected')
        ).toBe('true');
    });

    it('replaces the paragraph with the selected block when Enter is pressed', async () => {
        const block = paragraph('p1', '<p></p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.insertContent('/');
        });

        await act(async () => {
            fireEvent.keyDown(window, { key: 'ArrowDown' });
        });

        const tiptap = editor!.view.dom as HTMLElement;
        await act(async () => {
            fireEvent.keyDown(tiptap, { key: 'Enter' });
        });

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe('ve/heading');
    });

    it('closes the popover on Escape', async () => {
        const block = paragraph('p1', '<p></p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.insertContent('/');
        });

        expect(screen.queryByTestId('ve-slash-command-popover')).toBeInTheDocument();

        await act(async () => {
            fireEvent.keyDown(window, { key: 'Escape' });
        });

        expect(screen.queryByTestId('ve-slash-command-popover')).toBeNull();
    });

    it('replaces the paragraph when a popover item is clicked', async () => {
        const block = paragraph('p1', '<p></p>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('p1');
        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.insertContent('/');
        });

        await act(async () => {
            fireEvent.mouseDown(screen.getByTestId('ve-slash-command-item-ve/heading'));
        });

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe('ve/heading');
    });
});
