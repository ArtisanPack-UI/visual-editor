import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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

describe('BlockList drag-and-drop wiring', () => {
    it('renders accessible drag handle buttons for every top-level block', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
            makeBlock('c', 'gamma'),
        ]);

        renderList(store);

        const handles = document.querySelectorAll('[data-ve-drag-handle-slot]');
        expect(handles.length).toBe(3);
        handles.forEach((handle) => {
            expect(handle.tagName).toBe('BUTTON');
            expect(handle.getAttribute('aria-label')).toMatch(/Drag block/);
        });
    });

    it('registers pointer and keyboard sensors without throwing on mount', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);

        expect(() => renderList(store)).not.toThrow();
        expect(
            document.querySelectorAll('[data-ve-block-wrapper]').length
        ).toBe(2);
    });

    it('preserves click-to-select selection capture inside the DndContext', async () => {
        const user = userEvent.setup();
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);

        renderList(store);

        await user.click(screen.getByText('beta'));

        expect(store.getState().selection.clientId).toBe('b');
    });

    it('exposes screen reader instructions covering keyboard drag', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);

        renderList(store);

        const liveRegions = document.querySelectorAll('[aria-live]');
        expect(liveRegions.length).toBeGreaterThan(0);
        expect(document.body.textContent ?? '').toMatch(
            /press space or enter/i
        );
    });

    it('keeps the drag handle focusable via keyboard navigation', async () => {
        const user = userEvent.setup();
        const store = createEditorStore([makeBlock('a', 'alpha')]);

        renderList(store);
        await user.tab();

        const focused = document.activeElement as HTMLElement | null;
        expect(focused?.getAttribute('data-ve-drag-handle-slot')).not.toBeNull();
    });
});
