/**
 * Block renderer registry.
 *
 * The registry maps a block name (e.g. `core/paragraph`, `acme/my-block`) to
 * a Vue component. Every renderer receives the same
 * {@link BlockRendererProps} contract: parsed `attributes`, the raw
 * `innerBlocks` array, plus already-rendered inner-block children via the
 * default slot.
 *
 * Core blocks register themselves at import time from `registerCoreBlocks.ts`.
 * Host apps and third-party packages register custom renderers via
 * {@link registerBlockRenderer}, which also lets them override a core block.
 */

import type { BlockRenderer } from './types';

const registry = new Map<string, BlockRenderer>();

export function registerBlockRenderer(name: string, renderer: BlockRenderer): void {
    const trimmed = name.trim();

    if (trimmed === '') {
        throw new Error('registerBlockRenderer: block name cannot be empty.');
    }

    registry.set(trimmed, renderer);
}

export function unregisterBlockRenderer(name: string): void {
    registry.delete(name.trim());
}

export function getBlockRenderer(name: string): BlockRenderer | undefined {
    return registry.get(name.trim());
}

export function hasBlockRenderer(name: string): boolean {
    return registry.has(name.trim());
}

export function getRegisteredBlockNames(): string[] {
    return Array.from(registry.keys()).sort();
}

export function resetBlockRegistry(): void {
    registry.clear();
}
