/**
 * Shared types for the React block renderer.
 *
 * A {@link Block} matches the shape the visual editor persists to any
 * `HasBlockContent` model: `{ clientId, name, attributes, innerBlocks }`.
 * Block renderer components receive their own parsed attributes plus the
 * already-rendered inner blocks (as React children) so containers can splice
 * children into place without re-walking the tree.
 */

import type { ComponentType, ReactNode } from 'react';

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
    children?: ReactNode;
}

export type BlockRenderer = ComponentType<BlockRendererProps>;
