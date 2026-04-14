import { useEffect } from 'react';
import { useStore } from 'zustand';
import type { EditorStore } from '../store';
import { EditorStoreProvider, useEditorStore } from '../primitives';
import { Canvas } from './Canvas';
import { BlockList } from './BlockList';

export interface EditorShellProps {
    store: EditorStore;
}

export function EditorShell({ store }: EditorShellProps) {
    return (
        <EditorStoreProvider store={store}>
            <EditorShellChrome />
        </EditorStoreProvider>
    );
}

function EditorShellChrome() {
    return (
        <div className="ve-editor-shell" data-ve-editor-shell="">
            <Statusbar />
            <KeyboardBindings />
            <Canvas>
                <BlockList />
            </Canvas>
        </div>
    );
}

function Statusbar() {
    const store = useEditorStore();
    const isDirty = useStore(store, (state) => state.isDirty);
    const selectedClientId = useStore(store, (state) => state.selection.clientId);

    return (
        <div className="ve-editor-shell__statusbar" role="status" aria-live="polite">
            <span
                className={[
                    've-editor-shell__status',
                    isDirty ? 've-editor-shell__status--dirty' : null,
                ]
                    .filter(Boolean)
                    .join(' ')}
                data-ve-dirty={isDirty || undefined}
            >
                {isDirty ? 'Unsaved changes' : 'Saved'}
            </span>
            <span
                className="ve-editor-shell__status"
                data-ve-selection={selectedClientId ?? undefined}
            >
                {selectedClientId ? `Selected: ${selectedClientId}` : 'No selection'}
            </span>
        </div>
    );
}

function KeyboardBindings() {
    const store = useEditorStore();

    useEffect(() => {
        function handleKeyDown(event: KeyboardEvent) {
            if (!isUndoRedoKey(event)) {
                return;
            }

            const isRedo = event.shiftKey || event.key.toLowerCase() === 'y';

            event.preventDefault();

            if (isRedo) {
                store.getState().redo();
            } else {
                store.getState().undo();
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [store]);

    return null;
}

function isUndoRedoKey(event: KeyboardEvent): boolean {
    if (!(event.metaKey || event.ctrlKey)) {
        return false;
    }

    const key = event.key.toLowerCase();

    return key === 'z' || key === 'y';
}
