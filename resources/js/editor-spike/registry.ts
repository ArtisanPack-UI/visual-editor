import type { ComponentType } from 'react';
import type { Block } from './mocks/blockTree';
import type { BlockContextValue } from './primitives/BlockContext';

export interface BlockEditProps {
    clientId: string;
    attributes: Record<string, unknown>;
    block: Block;
}

export interface BlockDefinition {
    name: string;
    edit: ComponentType<BlockEditProps>;
    providesContext?: (attributes: Record<string, unknown>, block: Block) => BlockContextValue;
}

const registry = new Map<string, BlockDefinition>();

export function registerBlock(definition: BlockDefinition): void {
    registry.set(definition.name, definition);
}

export function getBlock(name: string): BlockDefinition | undefined {
    return registry.get(name);
}

export function unregisterBlock(name: string): void {
    registry.delete(name);
}

export function clearRegistry(): void {
    registry.clear();
}

export function getRegisteredBlockNames(): string[] {
    return Array.from(registry.keys());
}
