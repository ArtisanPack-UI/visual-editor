import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { EditorStoreProvider } from '../../primitives';
import { createEditorStore, type Block, type EditorStore } from '../../store';
import { InserterPanel } from '../InserterPanel';
import {
    clearInserterRegistry,
    registerBuiltinInserterBlocks,
} from '../../inserter';

function paragraph(clientId: string, content: string): Block {
    return {
        clientId,
        name: 've/paragraph',
        attributes: { content },
        innerBlocks: [],
    };
}

function renderPanel(store: EditorStore) {
    return render(
        <EditorStoreProvider store={store}>
            <InserterPanel />
        </EditorStoreProvider>
    );
}

beforeEach(() => {
    clearInserterRegistry();
    registerBuiltinInserterBlocks();
});

afterEach(() => {
    clearInserterRegistry();
});

describe('InserterPanel', () => {
    it('lists the built-in paragraph and heading blocks', () => {
        const store = createEditorStore([]);

        renderPanel(store);

        expect(screen.getByTestId('ve-inserter-item-ve/paragraph')).toBeInTheDocument();
        expect(screen.getByTestId('ve-inserter-item-ve/heading')).toBeInTheDocument();
    });

    it('filters the list by the search query', () => {
        const store = createEditorStore([]);

        renderPanel(store);

        const search = screen.getByTestId('ve-inserter-search') as HTMLInputElement;

        act(() => {
            fireEvent.change(search, { target: { value: 'head' } });
        });

        expect(screen.queryByTestId('ve-inserter-item-ve/paragraph')).toBeNull();
        expect(screen.getByTestId('ve-inserter-item-ve/heading')).toBeInTheDocument();
    });

    it('shows an empty state when the query matches nothing', () => {
        const store = createEditorStore([]);

        renderPanel(store);

        const search = screen.getByTestId('ve-inserter-search') as HTMLInputElement;

        act(() => {
            fireEvent.change(search, { target: { value: 'zzz' } });
        });

        expect(screen.getByTestId('ve-inserter-empty')).toBeInTheDocument();
    });

    it('inserts a block after the current selection when clicked', () => {
        const store = createEditorStore([paragraph('p1', '<p>a</p>')]);
        store.getState().select('p1');

        renderPanel(store);

        act(() => {
            fireEvent.click(screen.getByTestId('ve-inserter-item-ve/heading'));
        });

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[1].name).toBe('ve/heading');
        expect(store.getState().selection.clientId).toBe(blocks[1].clientId);
    });

    it('appends a block when nothing is selected', () => {
        const store = createEditorStore([paragraph('p1', '<p>a</p>')]);

        renderPanel(store);

        act(() => {
            fireEvent.click(screen.getByTestId('ve-inserter-item-ve/paragraph'));
        });

        const blocks = store.getState().blocks;
        expect(blocks).toHaveLength(2);
        expect(blocks[1].name).toBe('ve/paragraph');
    });
});
