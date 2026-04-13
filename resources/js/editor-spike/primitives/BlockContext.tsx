import { createContext, useContext, useMemo, type ReactNode } from 'react';

export type BlockContextValue = Record<string, unknown>;

const Context = createContext<BlockContextValue>({});
Context.displayName = 'BlockContext';

export interface BlockContextProviderProps {
    value: BlockContextValue;
    children: ReactNode;
}

export function BlockContextProvider({ value, children }: BlockContextProviderProps) {
    const parent = useContext(Context);
    const merged = useMemo(() => ({ ...parent, ...value }), [parent, value]);

    return <Context.Provider value={merged}>{children}</Context.Provider>;
}

export function useBlockContext(): BlockContextValue {
    return useContext(Context);
}

export function useBlockContextValue<T>(key: string): T | undefined {
    const context = useContext(Context);
    return context[key] as T | undefined;
}

export default Context;
