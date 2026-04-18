import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerCoreBlocks, PARAGRAPH_BLOCK_NAME, HEADING_BLOCK_NAME } from '../index';
import {
    clearBlockEditors,
    getBlockEditor,
} from '../shared/blockEditorRegistry';
import { clearInserterRegistry } from '../../inserter';

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
        expect(screen.getByTestId(`ve-slash-command-item-${PARAGRAPH_BLOCK_NAME}`)).toBeInTheDocument();
        expect(screen.getByTestId(`ve-slash-command-item-${HEADING_BLOCK_NAME}`)).toBeInTheDocument();
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

        expect(screen.queryByTestId(`ve-slash-command-item-${PARAGRAPH_BLOCK_NAME}`)).toBeNull();
        expect(screen.getByTestId(`ve-slash-command-item-${HEADING_BLOCK_NAME}`)).toBeInTheDocument();
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
                .getByTestId(`ve-slash-command-item-${PARAGRAPH_BLOCK_NAME}`)
                .getAttribute('data-selected')
        ).toBe('true');

        await act(async () => {
            fireEvent.keyDown(window, { key: 'ArrowDown' });
        });

        expect(
            screen
                .getByTestId(`ve-slash-command-item-${HEADING_BLOCK_NAME}`)
                .getAttribute('data-selected')
        ).toBe('true');

        await act(async () => {
            fireEvent.keyDown(window, { key: 'ArrowUp' });
        });

        expect(
            screen
                .getByTestId(`ve-slash-command-item-${PARAGRAPH_BLOCK_NAME}`)
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
        expect(blocks[0].name).toBe(HEADING_BLOCK_NAME);
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
            fireEvent.mouseDown(screen.getByTestId(`ve-slash-command-item-${HEADING_BLOCK_NAME}`));
        });

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe(HEADING_BLOCK_NAME);
    });
});
