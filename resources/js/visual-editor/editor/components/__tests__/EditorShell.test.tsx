import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { EditorShell } from '../EditorShell';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../../registry';
import { createEditorStore, type Block } from '../../store';

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

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 've/paragraph', edit: Paragraph });
});

afterEach(() => {
    clearRegistry();
});

describe('EditorShell', () => {
    it('mounts the Canvas and BlockList from the store', () => {
        const store = createEditorStore([
            makeBlock('a', 'alpha'),
            makeBlock('b', 'beta'),
        ]);

        render(<EditorShell store={store} />);

        expect(document.querySelector('[data-ve-editor-shell]')).not.toBeNull();
        expect(document.querySelector('[data-ve-canvas]')).not.toBeNull();
        expect(screen.getByText('alpha')).toBeInTheDocument();
        expect(screen.getByText('beta')).toBeInTheDocument();
    });

    it('shows "Saved" in the statusbar and switches to "Unsaved changes" on mutation', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        expect(screen.getByText('Saved')).toBeInTheDocument();

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        expect(screen.getByText('Unsaved changes')).toBeInTheDocument();
    });

    it('shows the currently selected block client id', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        expect(screen.getByText('No selection')).toBeInTheDocument();

        act(() => {
            store.getState().select('a');
        });

        expect(screen.getByText('Selected: a')).toBeInTheDocument();
    });

    it('undoes the last mutation when Cmd+Z is pressed', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a', 'b']);

        act(() => {
            fireEvent.keyDown(window, { key: 'z', metaKey: true });
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a']);
    });

    it('undoes the last mutation when Ctrl+Z is pressed', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        act(() => {
            fireEvent.keyDown(window, { key: 'z', ctrlKey: true });
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a']);
    });

    it('redoes when Cmd+Shift+Z is pressed', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        act(() => {
            store.getState().undo();
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a']);

        act(() => {
            fireEvent.keyDown(window, { key: 'z', metaKey: true, shiftKey: true });
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a', 'b']);
    });

    it('redoes when Ctrl+Y is pressed', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        act(() => {
            store.getState().undo();
        });

        act(() => {
            fireEvent.keyDown(window, { key: 'y', ctrlKey: true });
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a', 'b']);
    });

    it('ignores keydowns without meta or ctrl modifiers', () => {
        const store = createEditorStore([makeBlock('a', 'alpha')]);
        render(<EditorShell store={store} />);

        act(() => {
            store.getState().insertBlock(makeBlock('b', 'beta'));
        });

        act(() => {
            fireEvent.keyDown(window, { key: 'z' });
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['a', 'b']);
    });
});
