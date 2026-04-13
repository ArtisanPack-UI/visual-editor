import { createContext, useContext, type ReactNode } from 'react';
import type { EditorStore } from '../store';

const Context = createContext<EditorStore | null>(null);
Context.displayName = 'EditorStoreContext';

export interface EditorStoreProviderProps {
    store: EditorStore;
    children: ReactNode;
}

export function EditorStoreProvider({ store, children }: EditorStoreProviderProps) {
    return <Context.Provider value={store}>{children}</Context.Provider>;
}

export function useEditorStore(): EditorStore {
    const store = useContext(Context);

    if (store === null) {
        throw new Error(
            'useEditorStore must be used within an <EditorStoreProvider>. ' +
                'Wrap your editor tree with <EditorStoreProvider store={store}> to provide a store instance.'
        );
    }

    return store;
}

export function useOptionalEditorStore(): EditorStore | null {
    return useContext(Context);
}
