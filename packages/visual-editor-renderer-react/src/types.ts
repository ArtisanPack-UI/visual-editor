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
    /**
     * Rendered inner blocks routed to a named slot instead of
     * `children`. The template-part inliner's overlay-content block is a
     * slot container: its own inner blocks render into `slots[_slot]`
     * (#804 — the navigation overlay).
     */
    slots?: Record<string, ReactNode>;
}

export type BlockRenderer = ComponentType<BlockRendererProps>;
