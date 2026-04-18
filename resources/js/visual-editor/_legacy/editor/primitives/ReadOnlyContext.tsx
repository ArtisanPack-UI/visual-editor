import { createContext, useContext, type ReactNode } from 'react';

const Context = createContext<boolean>(false);
Context.displayName = 'ReadOnlyContext';

export interface ReadOnlyProviderProps {
    value: boolean;
    children: ReactNode;
}

export function ReadOnlyProvider({ value, children }: ReadOnlyProviderProps) {
    return <Context.Provider value={value}>{children}</Context.Provider>;
}

export function useReadOnly(): boolean {
    return useContext(Context);
}

export default Context;
