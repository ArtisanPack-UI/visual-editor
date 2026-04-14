import { createContext, useContext, useMemo, useRef, type ReactNode } from 'react';

/**
 * A runtime-agnostic block context map. Values are typed as `unknown` so that
 * readers must narrow through a type guard — no `as T` escape hatches.
 */
export type BlockContextValue = Readonly<Record<string, unknown>>;

export type BlockContextTypeGuard<T> = (value: unknown) => value is T;

const EMPTY_CONTEXT: BlockContextValue = Object.freeze({});

const UNSET_SENTINEL: unique symbol = Symbol('useBlockContextValue.unset');

const Context = createContext<BlockContextValue>(EMPTY_CONTEXT);
Context.displayName = 'BlockContext';

export interface BlockContextProviderProps {
    value: BlockContextValue;
    children: ReactNode;
}

export function BlockContextProvider({ value, children }: BlockContextProviderProps) {
    const parent = useContext(Context);
    const merged = useMemo<BlockContextValue>(
        () => Object.freeze({ ...parent, ...value }),
        [parent, value]
    );

    return <Context.Provider value={merged}>{children}</Context.Provider>;
}

export function useBlockContext(): BlockContextValue {
    return useContext(Context);
}

/**
 * Read a typed value from the nearest BlockContextProvider, narrowing the value
 * with a runtime type guard. When the key is missing or the guard rejects the
 * value, returns `undefined`. The returned reference is stable across renders
 * until the underlying context value changes, so memoized consumers stay
 * memoized.
 */
export function useBlockContextValue<T>(
    key: string,
    guard: BlockContextTypeGuard<T>
): T | undefined {
    const context = useContext(Context);
    const raw = context[key];

    const lastRawRef = useRef<unknown>(UNSET_SENTINEL);
    const lastGuardRef = useRef<BlockContextTypeGuard<T> | null>(null);
    const lastResultRef = useRef<T | undefined>(undefined);

    if (
        !Object.is(lastRawRef.current, raw) ||
        lastGuardRef.current !== guard
    ) {
        lastRawRef.current = raw;
        lastGuardRef.current = guard;
        lastResultRef.current = guard(raw) ? raw : undefined;
    }

    return lastResultRef.current;
}

export default Context;
