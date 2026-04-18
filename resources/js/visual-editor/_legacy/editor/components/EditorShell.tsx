import { useCallback, useEffect, useState } from 'react';
import type { EditorStore } from '../store';
import type { AutosaveState } from '../rest';
import { EditorStoreProvider, useEditorStore } from '../primitives';
import { Canvas } from './Canvas';
import { BlockList } from './BlockList';
import { TopBar } from './TopBar';
import { StatusBar } from './StatusBar';
import { InspectorSidebar } from './InspectorSidebar';
import { LeftSidebar } from './LeftSidebar';
import { BlockToolbar } from './BlockToolbar';

const TOOLBAR_PINNED_KEY = 've-toolbar-pinned';

function readToolbarPinned(): boolean {
    try {
        return localStorage.getItem(TOOLBAR_PINNED_KEY) === 'true';
    } catch {
        return false;
    }
}

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
    const [inserterOpen, setInserterOpen] = useState(false);
    const [inspectorOpen, setInspectorOpen] = useState(true);
    const [toolbarPinned, setToolbarPinned] = useState(readToolbarPinned);

    const toggleInserter = useCallback(() => {
        setInserterOpen((prev) => !prev);
    }, []);

    const toggleInspector = useCallback(() => {
        setInspectorOpen((prev) => !prev);
    }, []);

    const toggleToolbarPin = useCallback(() => {
        setToolbarPinned((prev) => {
            const next = !prev;
            try {
                localStorage.setItem(TOOLBAR_PINNED_KEY, String(next));
            } catch {
                // localStorage unavailable
            }
            return next;
        });
    }, []);

    return (
        <div
            className={[
                've-editor-shell',
                inserterOpen ? 've-editor-shell--inserter-open' : null,
                inspectorOpen ? 've-editor-shell--inspector-open' : null,
            ].filter(Boolean).join(' ')}
            data-ve-editor-shell=""
        >
            <TopBar
                inserterOpen={inserterOpen}
                onToggleInserter={toggleInserter}
                inspectorOpen={inspectorOpen}
                onToggleInspector={toggleInspector}
                saveStatus={saveStatus}
                pinnedToolbar={
                    toolbarPinned ? (
                        <BlockToolbar pinned onTogglePin={toggleToolbarPin} />
                    ) : null
                }
            />

            <KeyboardBindings />

            <div className="ve-editor-shell__body">
                {inserterOpen ? <LeftSidebar /> : null}

                <div className="ve-editor-shell__canvas-area" style={{ position: 'relative' }}>
                    {!toolbarPinned ? (
                        <BlockToolbar pinned={false} onTogglePin={toggleToolbarPin} />
                    ) : null}
                    <Canvas>
                        <BlockList />
                    </Canvas>
                </div>

                <InspectorSidebar open={inspectorOpen} />
            </div>

            <StatusBar />
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

    return false;
}
