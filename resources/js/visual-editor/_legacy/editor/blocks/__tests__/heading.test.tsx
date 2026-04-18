import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry, getBlock } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import {
    registerHeadingBlock,
    HEADING_BLOCK_NAME,
    normalizeHeadingLevel,
} from '../heading';
import { clearBlockEditors, getBlockEditor } from '../shared/blockEditorRegistry';

function makeHeading(clientId: string, content: string, level = 2): Block {
    return {
        clientId,
        name: HEADING_BLOCK_NAME,
        attributes: { level, content },
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
    registerHeadingBlock();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('heading block registration', () => {
    it('registers ve/heading in the block registry', () => {
        expect(getBlock(HEADING_BLOCK_NAME)).toBeDefined();
    });
});

describe('normalizeHeadingLevel', () => {
    it('accepts levels 1–6 and defaults invalid values to 2', () => {
        expect(normalizeHeadingLevel(1)).toBe(1);
        expect(normalizeHeadingLevel(6)).toBe(6);
        expect(normalizeHeadingLevel(7)).toBe(2);
        expect(normalizeHeadingLevel('3')).toBe(2);
        expect(normalizeHeadingLevel(undefined)).toBe(2);
    });
});

describe('heading block edit', () => {
    it('renders the initial content from attributes.content', () => {
        const block = makeHeading('h1', '<h2>Hello World</h2>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        expect(screen.getByText('Hello World')).toBeInTheDocument();
    });

    it('updates the rendered heading level when attributes.level changes', async () => {
        const block = makeHeading('h1', '<h2>Title</h2>', 2);
        const store = createEditorStore([block]);

        renderBlock(store, block);

        act(() => {
            store.getState().updateBlockAttributes('h1', { level: 4 });
        });

        const editor = getBlockEditor('h1');
        expect(editor).not.toBeNull();
        expect(editor!.state.doc.firstChild?.attrs.level).toBe(4);
    });

    it('writes content back to the store when the editor updates', async () => {
        const block = makeHeading('h1', '<h2>initial</h2>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('h1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.insertContentAt(1, 'edited ');
        });

        const content = store.getState().blocks[0].attributes.content as string;
        expect(content).toContain('edited');
        expect(content).toContain('initial');
    });
});
