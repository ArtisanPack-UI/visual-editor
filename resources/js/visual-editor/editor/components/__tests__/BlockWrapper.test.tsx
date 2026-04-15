import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { BlockWrapper } from '../BlockWrapper';
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

function makeBlock(clientId: string): Block {
    return {
        clientId,
        name: 've/paragraph',
        attributes: { content: `content-${clientId}` },
        innerBlocks: [],
    };
}

function renderWithStore(store: EditorStore, block: Block) {
    return render(
        <EditorStoreProvider store={store}>
            <BlockWrapper block={block} />
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

describe('BlockWrapper', () => {
    it('renders the block via RenderBlock inside the wrapper', () => {
        const store = createEditorStore([makeBlock('a')]);
        renderWithStore(store, makeBlock('a'));

        expect(screen.getByText('content-a')).toBeInTheDocument();
        expect(document.querySelector('[data-ve-block-wrapper]')).not.toBeNull();
    });

    it('exposes a drag-handle slot element', () => {
        const store = createEditorStore([makeBlock('a')]);
        renderWithStore(store, makeBlock('a'));

        expect(document.querySelector('[data-ve-drag-handle-slot]')).not.toBeNull();
    });

    it('applies the selected class when the store selection matches', () => {
        const store = createEditorStore([makeBlock('a')]);
        const { container } = renderWithStore(store, makeBlock('a'));

        const wrapper = container.querySelector('[data-ve-block-wrapper]') as HTMLElement;
        expect(wrapper.className).not.toContain('ve-block--is-selected');

        act(() => {
            store.getState().select('a');
        });

        expect(wrapper.className).toContain('ve-block--is-selected');
        expect(wrapper.dataset.veSelected).toBe('true');
    });

    it('toggles the hovered class on mouse enter and leave', () => {
        const store = createEditorStore([makeBlock('a')]);
        const { container } = renderWithStore(store, makeBlock('a'));

        const wrapper = container.querySelector('[data-ve-block-wrapper]') as HTMLElement;

        fireEvent.mouseEnter(wrapper);
        expect(wrapper.className).toContain('ve-block--is-hovered');

        fireEvent.mouseLeave(wrapper);
        expect(wrapper.className).not.toContain('ve-block--is-hovered');
    });
});
