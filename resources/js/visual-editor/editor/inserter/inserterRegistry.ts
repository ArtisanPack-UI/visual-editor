import type { Block } from '../store';

export interface InserterBlock {
    name: string;
    title: string;
    description?: string;
    keywords?: readonly string[];
}

export type BlockFactory = () => Omit<Block, 'clientId'>;

const inserterBlocks = new Map<string, InserterBlock>();
const blockFactories = new Map<string, BlockFactory>();
const listeners = new Set<() => void>();

let cachedSnapshot: readonly InserterBlock[] = Object.freeze([]);

function invalidateSnapshot(): void {
    cachedSnapshot = Object.freeze(Array.from(inserterBlocks.values()));
    listeners.forEach((listener) => listener());
}

export function registerInserterBlock(block: InserterBlock): void {
    inserterBlocks.set(block.name, block);
    invalidateSnapshot();
}

export function registerBlockFactory(name: string, factory: BlockFactory): void {
    blockFactories.set(name, factory);
}

export function getInserterBlocks(): readonly InserterBlock[] {
    return cachedSnapshot;
}

export function getBlockFactory(name: string): BlockFactory | undefined {
    return blockFactories.get(name);
}

export function clearInserterRegistry(): void {
    inserterBlocks.clear();
    blockFactories.clear();
    invalidateSnapshot();
}

export function subscribeInserterBlocks(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}
