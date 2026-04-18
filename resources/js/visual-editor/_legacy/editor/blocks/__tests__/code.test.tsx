import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry, getBlock } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerCodeBlock, CODE_BLOCK_NAME, normalizeLanguage } from '../code';
import { clearBlockEditors } from '../shared/blockEditorRegistry';

function makeCode(clientId: string, content: string, language = 'plaintext'): Block {
    return {
        clientId,
        name: CODE_BLOCK_NAME,
        attributes: { content, language },
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
    registerCodeBlock();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('code block registration', () => {
    it('registers artisanpack/code in the block registry', () => {
        expect(getBlock(CODE_BLOCK_NAME)).toBeDefined();
    });
});

describe('normalizeLanguage', () => {
    it('accepts supported languages and defaults unknown to plaintext', () => {
        expect(normalizeLanguage('php')).toBe('php');
        expect(normalizeLanguage('unknown')).toBe('plaintext');
        expect(normalizeLanguage(undefined)).toBe('plaintext');
    });
});

describe('code block edit', () => {
    it('renders initial content in textarea', () => {
        const block = makeCode('c1', 'const x = 1;');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const textarea = screen.getByTestId('ve-code-textarea') as HTMLTextAreaElement;
        expect(textarea.value).toBe('const x = 1;');
    });

    it('writes content changes to the store', () => {
        const block = makeCode('c1', 'initial');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const textarea = screen.getByTestId('ve-code-textarea') as HTMLTextAreaElement;
        fireEvent.change(textarea, { target: { value: 'updated code' } });

        expect(store.getState().blocks[0].attributes.content).toBe('updated code');
    });

    it('writes language changes to the store', () => {
        const block = makeCode('c1', '', 'plaintext');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const select = screen.getByTestId('ve-code-language') as HTMLSelectElement;
        fireEvent.change(select, { target: { value: 'javascript' } });

        expect(store.getState().blocks[0].attributes.language).toBe('javascript');
    });
});
