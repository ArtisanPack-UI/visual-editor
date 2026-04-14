import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { BlockList } from '../BlockList';
import { EditorStoreProvider } from '../../primitives';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';

function Paragraph({ attributes, clientId }: BlockEditProps) {
    return <p data-client-id={clientId}>{String(attributes.content ?? '')}</p>;
}

function makeBlock(clientId: string, content: string): Block {
    return {
        clientId,
        name: 've/paragraph',
        attributes: { content },
        innerBlocks: [],
    };
}

function renderList(store: EditorStore) {
    return render(
        <EditorStoreProvider store={store}>
            <BlockList />
        </EditorStoreProvider>
    );
}

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 've/paragraph', edit: Paragraph });
});

afterEach(() => {
    clearRegistry();
});

describe('BlockList', () => {
    it('renders a BlockWrapper for each top-level block', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);

        renderList(store);

        expect(screen.getByText('alpha')).toBeInTheDocument();
        expect(screen.getByText('beta')).toBeInTheDocument();
        expect(document.querySelectorAll('[data-ve-block-wrapper]').length).toBe(2);
    });

    it('selects a block via the store when a child is clicked', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);

        renderList(store);

        fireEvent.click(screen.getByText('beta'));

        expect(store.getState().selection.clientId).toBe('b');
    });

    it('reactively renders blocks inserted into the store', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        renderList(store);

        act(() => {
            store.getState().insertBlock(makeBlock('c', 'gamma'));
        });

        expect(screen.getByText('gamma')).toBeInTheDocument();
    });

    it('reflects store-driven selection in the wrapper state', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        const { container } = renderList(store);

        act(() => {
            store.getState().select('a');
        });

        const wrapper = container.querySelector('[data-ve-block-wrapper]') as HTMLElement;
        expect(wrapper.className).toContain('ve-block--is-selected');
    });
});
