import { useEffect } from 'react';
import { useStore } from 'zustand';
import type { EditorStore } from '../store';
import type { AutosaveState } from '../rest';
import { EditorStoreProvider, useEditorStore } from '../primitives';
import { Canvas } from './Canvas';
import { BlockList } from './BlockList';
import { RichTextToolbar } from './RichTextToolbar';
import { InserterPanel } from './InserterPanel';

export interface EditorShellProps {
    store: EditorStore;
    saveStatus?: AutosaveState;
}

export function EditorShell({ store, saveStatus }: EditorShellProps) {
    return (
        <EditorStoreProvider store={store}>
            <EditorShellChrome saveStatus={saveStatus} />
        </EditorStoreProvider>
    );
}

function EditorShellChrome({ saveStatus }: { saveStatus?: AutosaveState }) {
    return (
        <div className="ve-editor-shell" data-ve-editor-shell="">
            <Statusbar saveStatus={saveStatus} />
            <KeyboardBindings />
            <RichTextToolbar />
            <div className="ve-editor-shell__body">
                <InserterPanel />
                <Canvas>
                    <BlockList />
                </Canvas>
            </div>
        </div>
    );
}

function Statusbar({ saveStatus }: { saveStatus?: AutosaveState }) {
    const store = useEditorStore();
    const isDirty = useStore(store, (state) => state.isDirty);
    const selectedClientId = useStore(store, (state) => state.selection.clientId);

    const statusLabel = resolveStatusLabel(saveStatus, isDirty);
    const statusToken = saveStatus?.status ?? (isDirty ? 'dirty' : 'saved');

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
                data-ve-save-status={statusToken}
            >
                {statusLabel}
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

            if (shouldDeferToNativeHistory(event.target)) {
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

function resolveStatusLabel(
    saveStatus: AutosaveState | undefined,
    isDirty: boolean
): string {
    if (saveStatus) {
        switch (saveStatus.status) {
            case 'saving':
                return 'Saving…';
            case 'saved':
                return 'Saved';
            case 'error':
                return 'Save failed';
            case 'idle':
            default:
                break;
        }
    }

    return isDirty ? 'Unsaved changes' : 'Saved';
}

function isUndoRedoKey(event: KeyboardEvent): boolean {
    if (!(event.metaKey || event.ctrlKey)) {
        return false;
    }

    const key = event.key.toLowerCase();

    return key === 'z' || key === 'y';
}

function shouldDeferToNativeHistory(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        return true;
    }

    // Non-contenteditable focusable fields (e.g. `<select>`) should also keep
    // their native behavior. The Tiptap editor surfaces are contenteditable,
    // so those still route through the store's undo/redo.
    return false;
}
