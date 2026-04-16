import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry, getBlock } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerQuoteBlock, QUOTE_BLOCK_NAME } from '../quote';
import {
    clearBlockEditors,
    getBlockEditor,
} from '../shared/blockEditorRegistry';

function makeQuote(
    clientId: string,
    content: string,
    citation = ''
): Block {
    return {
        clientId,
        name: QUOTE_BLOCK_NAME,
        attributes: { content, citation },
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
    registerQuoteBlock();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('quote block registration', () => {
    it('registers ve/quote in the block registry', () => {
        expect(getBlock(QUOTE_BLOCK_NAME)).toBeDefined();
    });
});

describe('quote block edit', () => {
    it('renders the initial content from attributes.content', () => {
        const block = makeQuote('q1', '<blockquote><p>Hello quote</p></blockquote>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        expect(screen.getByText('Hello quote')).toBeInTheDocument();
    });

    it('writes content updates back to the store', async () => {
        const block = makeQuote('q1', '<blockquote><p>initial</p></blockquote>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('q1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.focus('end');
            editor!.commands.insertContent(' more');
        });

        const content = store.getState().blocks[0].attributes.content as string;
        expect(content).toContain('initial');
        expect(content).toContain('more');
    });

    it('writes citation changes back to the store', () => {
        const block = makeQuote('q1', '<blockquote><p>body</p></blockquote>', '');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const input = screen.getByTestId('ve-quote-citation') as HTMLInputElement;
        fireEvent.change(input, { target: { value: 'Jane Doe' } });

        expect(store.getState().blocks[0].attributes.citation).toBe('Jane Doe');
    });
});
