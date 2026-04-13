import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { EditorStoreProvider, useEditorStore } from '../EditorStoreContext';
import { createEditorStore } from '../../store';

function StoreReader() {
    const store = useEditorStore();
    return <div data-testid="count">{store.getState().blocks.length}</div>;
}

describe('EditorStoreContext', () => {
    it('exposes the provided store to descendants', () => {
        const store = createEditorStore([
            { clientId: 'a', name: 've/paragraph', attributes: {}, innerBlocks: [] },
            { clientId: 'b', name: 've/paragraph', attributes: {}, innerBlocks: [] },
        ]);

        render(
            <EditorStoreProvider store={store}>
                <StoreReader />
            </EditorStoreProvider>
        );

        expect(screen.getByTestId('count').textContent).toBe('2');
    });

    it('throws a helpful error when used outside of a provider', () => {
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(() => render(<StoreReader />)).toThrow(
            /useEditorStore must be used within an <EditorStoreProvider>/
        );

        consoleError.mockRestore();
    });
});
