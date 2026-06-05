/**
 * Shared types for the Vue block renderer.
 *
 * A {@link Block} matches the shape the visual editor persists to any
 * `HasBlockContent` model: `{ clientId, name, attributes, innerBlocks }`.
 * Block renderer components receive their own parsed attributes via props and
 * the already-rendered inner blocks via the default slot, so containers can
 * splice children into place without re-walking the tree.
 */

import type { Component } from 'vue';

export interface Block {
    clientId?: string;
    name: string;
    attributes?: Record<string, unknown>;
    innerBlocks?: Block[];
}

export interface BlockRendererProps {
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: Block[];
}

export type BlockRenderer = Component<BlockRendererProps>;
