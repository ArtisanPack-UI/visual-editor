import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry, getBlock } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { registerPreformattedBlock, PREFORMATTED_BLOCK_NAME } from '../preformatted';
import { clearBlockEditors, getBlockEditor } from '../shared/blockEditorRegistry';

function makePreformatted(clientId: string, content: string): Block {
    return {
        clientId,
        name: PREFORMATTED_BLOCK_NAME,
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
    registerPreformattedBlock();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('preformatted block registration', () => {
    it('registers artisanpack/preformatted in the block registry', () => {
        expect(getBlock(PREFORMATTED_BLOCK_NAME)).toBeDefined();
    });
});

describe('preformatted block edit', () => {
    it('renders initial pre content', () => {
        const block = makePreformatted('pf1', '<pre>Hello world</pre>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        expect(screen.getByText('Hello world')).toBeInTheDocument();
    });

    it('writes updated content back to the store', async () => {
        const block = makePreformatted('pf1', '<pre>initial</pre>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('pf1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.focus('end');
            editor!.commands.insertContent(' more');
        });

        const content = store.getState().blocks[0].attributes.content as string;
        expect(content).toMatch(/^<pre/);
        expect(content).toContain('initial');
        expect(content).toContain('more');
    });

    it('supports bold inline formatting', async () => {
        const block = makePreformatted('pf1', '<pre>abc</pre>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('pf1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.focus('start');
            editor!.commands.selectAll();
            editor!.commands.toggleBold();
        });

        const content = store.getState().blocks[0].attributes.content as string;
        expect(content).toMatch(/^<pre/);
        expect(content).toContain('<strong>');
    });
});
