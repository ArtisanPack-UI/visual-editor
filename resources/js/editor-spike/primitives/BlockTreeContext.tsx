import { createContext, useContext, useMemo, type ReactNode } from 'react';
import type { Block } from '../mocks/blockTree';

export interface BlockTreeContextValue {
    getChildren: (parentClientId: string) => Block[];
    getBlock: (clientId: string) => Block | undefined;
}

const defaultValue: BlockTreeContextValue = {
    getChildren: () => [],
    getBlock: () => undefined,
};

const Context = createContext<BlockTreeContextValue>(defaultValue);
Context.displayName = 'BlockTreeContext';

function findInTree(tree: Block[], clientId: string): Block | undefined {
    for (const block of tree) {
        if (block.clientId === clientId) {
            return block;
        }

        const found = findInTree(block.innerBlocks, clientId);
        if (found) {
            return found;
        }
    }

    return undefined;
}

export interface BlockTreeProviderProps {
    blocks: Block[];
    children: ReactNode;
}

export function BlockTreeProvider({ blocks, children }: BlockTreeProviderProps) {
    const value = useMemo<BlockTreeContextValue>(
        () => ({
            getChildren: (parentClientId) => {
                const parent = findInTree(blocks, parentClientId);
                return parent ? parent.innerBlocks : [];
            },
            getBlock: (clientId) => findInTree(blocks, clientId),
        }),
        [blocks]
    );

    return <Context.Provider value={value}>{children}</Context.Provider>;
}

export function useBlockTree(): BlockTreeContextValue {
    return useContext(Context);
}

export default Context;
