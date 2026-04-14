import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerCoreBlocks, PARAGRAPH_BLOCK_NAME, HEADING_BLOCK_NAME } from '../../blocks';
import {
    clearBlockEditors,
    getBlockEditor,
} from '../../blocks/shared/blockEditorRegistry';
import { RichTextToolbar } from '../RichTextToolbar';

function paragraph(clientId: string, content: string): Block {
    return {
        clientId,
        name: PARAGRAPH_BLOCK_NAME,
        attributes: { content },
        innerBlocks: [],
    };
}

function heading(clientId: string, content: string, level = 2): Block {
    return {
        clientId,
        name: HEADING_BLOCK_NAME,
        attributes: { level, content },
        innerBlocks: [],
    };
}

function renderWithBlocks(store: EditorStore, blocks: Block[]) {
    return render(
        <EditorStoreProvider store={store}>
            <RichTextToolbar />
            {blocks.map((block) => (
                <RenderBlock key={block.clientId} block={block} />
            ))}
        </EditorStoreProvider>
    );
}

beforeEach(() => {
    clearRegistry();
    clearBlockEditors();
    registerCoreBlocks();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('RichTextToolbar', () => {
    it('does not render when nothing is selected', () => {
        const block = paragraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        expect(
            document.querySelector('[data-ve-rich-text-toolbar]')
        ).toBeNull();
    });

    it('renders bold and italic buttons for a selected paragraph', async () => {
        const block = paragraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        act(() => {
            store.getState().select('p1');
        });

        expect(screen.getByTestId('ve-toolbar-bold')).toBeInTheDocument();
        expect(screen.getByTestId('ve-toolbar-italic')).toBeInTheDocument();
        expect(screen.queryByTestId('ve-toolbar-heading-level')).toBeNull();
    });

    it('toggles bold on the active editor', async () => {
        const block = paragraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        act(() => {
            store.getState().select('p1');
        });

        const editor = getBlockEditor('p1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.selectAll();
        });

        await act(async () => {
            fireEvent.click(screen.getByTestId('ve-toolbar-bold'));
        });

        expect(editor!.isActive('bold')).toBe(true);
        expect(editor!.getHTML()).toContain('<strong>hello</strong>');
    });

    it('toggles italic on the active editor', async () => {
        const block = paragraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        act(() => {
            store.getState().select('p1');
        });

        const editor = getBlockEditor('p1');

        await act(async () => {
            editor!.commands.selectAll();
        });

        await act(async () => {
            fireEvent.click(screen.getByTestId('ve-toolbar-italic'));
        });

        expect(editor!.isActive('italic')).toBe(true);
    });

    it('creates a link via the link popover', async () => {
        const block = paragraph('p1', '<p>hello</p>');
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        act(() => {
            store.getState().select('p1');
        });

        const editor = getBlockEditor('p1');

        await act(async () => {
            editor!.commands.selectAll();
        });

        await act(async () => {
            fireEvent.click(screen.getByTestId('ve-toolbar-link-toggle'));
        });

        const input = screen.getByTestId('ve-toolbar-link-input') as HTMLInputElement;

        await act(async () => {
            fireEvent.change(input, { target: { value: 'https://example.com' } });
        });

        await act(async () => {
            fireEvent.click(screen.getByTestId('ve-toolbar-link-apply'));
        });

        expect(editor!.isActive('link')).toBe(true);
        expect(editor!.getAttributes('link').href).toBe('https://example.com');
        expect(
            screen.queryByTestId('ve-toolbar-link-popover')
        ).toBeNull();
    });

    it('removes a link via the link popover', async () => {
        const block = paragraph('p1', '<p><a href="https://example.com">hello</a></p>');
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        act(() => {
            store.getState().select('p1');
        });

        const editor = getBlockEditor('p1');

        await act(async () => {
            editor!.commands.selectAll();
        });

        await act(async () => {
            fireEvent.click(screen.getByTestId('ve-toolbar-link-toggle'));
        });

        await act(async () => {
            fireEvent.click(screen.getByTestId('ve-toolbar-link-remove'));
        });

        expect(editor!.isActive('link')).toBe(false);
    });

    it('exposes a heading level switcher for selected heading blocks', async () => {
        const block = heading('h1', '<h2>Title</h2>', 2);
        const store = createEditorStore([block]);

        renderWithBlocks(store, [block]);

        act(() => {
            store.getState().select('h1');
        });

        const levelSelect = screen.getByTestId('ve-toolbar-heading-level') as HTMLSelectElement;
        expect(levelSelect).toBeInTheDocument();
        expect(levelSelect.value).toBe('2');

        await act(async () => {
            fireEvent.change(levelSelect, { target: { value: '4' } });
        });

        expect(store.getState().blocks[0].attributes.level).toBe(4);
    });
});
