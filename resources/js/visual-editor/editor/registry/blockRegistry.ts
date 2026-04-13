import type { ComponentType } from 'react';
import type { Block } from '../store';
import type { BlockContextValue } from '../primitives/BlockContext';

export interface BlockEditProps {
    clientId: string;
    attributes: Record<string, unknown>;
    block: Block;
}

export interface BlockDefinition {
    name: string;
    edit: ComponentType<BlockEditProps>;
    providesContext?: (attributes: Record<string, unknown>, block: Block) => BlockContextValue;
    usesContext?: readonly string[];
}

const registry = new Map<string, BlockDefinition>();

export function registerBlock(definition: BlockDefinition): void {
    if (registry.has(definition.name)) {
        console.warn(
            `[visual-editor] Block "${definition.name}" is already registered; ignoring duplicate registration.`
        );
        return;
    }

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
