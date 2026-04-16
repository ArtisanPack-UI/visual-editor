import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, render } from '@testing-library/react';
import { EditorStoreProvider, RenderBlock } from '../../primitives';
import { clearRegistry, getBlock } from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import {
    registerListBlock,
    LIST_BLOCK_NAME,
    normalizeOrdered,
} from '../list';
import {
    clearBlockEditors,
    getBlockEditor,
} from '../shared/blockEditorRegistry';

function makeList(
    clientId: string,
    content: string,
    ordered = false
): Block {
    return {
        clientId,
        name: LIST_BLOCK_NAME,
        attributes: { ordered, content },
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
    registerListBlock();
});

afterEach(() => {
    clearRegistry();
    clearBlockEditors();
});

describe('list block registration', () => {
    it('registers ve/list in the block registry', () => {
        expect(getBlock(LIST_BLOCK_NAME)).toBeDefined();
    });
});

describe('normalizeOrdered', () => {
    it('only returns true for strict true, everything else false', () => {
        expect(normalizeOrdered(true)).toBe(true);
        expect(normalizeOrdered(false)).toBe(false);
        expect(normalizeOrdered(undefined)).toBe(false);
        expect(normalizeOrdered('true')).toBe(false);
        expect(normalizeOrdered(1)).toBe(false);
    });
});

describe('list block edit', () => {
    it('mounts and seeds an empty bullet list when content is empty', () => {
        const block = makeList('l1', '');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('l1');
        expect(editor).not.toBeNull();
        expect(editor!.state.doc.firstChild?.type.name).toBe('bulletList');
    });

    it('writes updated list content back to the store', async () => {
        const block = makeList('l1', '<ul><li><p>first</p></li></ul>');
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('l1');
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.focus('end');
            editor!.commands.insertContent(' more');
        });

        const content = store.getState().blocks[0].attributes.content as string;
        expect(content).toContain('first');
        expect(content).toContain('more');
    });

    it('switches top-level node type when the ordered attribute changes', async () => {
        const block = makeList('l1', '<ul><li><p>item</p></li></ul>', false);
        const store = createEditorStore([block]);

        renderBlock(store, block);

        const editor = getBlockEditor('l1');
        expect(editor).not.toBeNull();
        expect(editor!.state.doc.firstChild?.type.name).toBe('bulletList');

        act(() => {
            store.getState().updateBlockAttributes('l1', { ordered: true });
        });

        expect(editor!.state.doc.firstChild?.type.name).toBe('orderedList');
    });
});
