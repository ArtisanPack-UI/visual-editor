/**
 * Public React component for rendering a saved block tree.
 *
 * Walks a `{ clientId, name, attributes, innerBlocks }` block tree the visual
 * editor persists and renders it to React elements. Each block name is looked
 * up in the shared block registry; anything unregistered falls through to the
 * {@link DynamicBlock} component, which fetches the server-rendered HTML from
 * the visual-editor preview endpoint.
 *
 * `tree` accepts the array form the editor persists, a JSON-encoded string of
 * that shape, or `null`/`undefined` for an empty render.
 */

import { Fragment } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { DynamicBlock } from './DynamicBlock';
import { UnknownBlock } from './blocks/unknownBlock';
import { getBlockRenderer } from './registry';
import type { Block } from './types';

const DEFAULT_ENDPOINT = '/visual-editor/api/blocks/preview';

export interface BlockTreeProps {
    tree: Block[] | string | null | undefined;
    dynamicBlockEndpoint?: string;
    fetchOptions?: RequestInit;
}

export function BlockTree({
    tree,
    dynamicBlockEndpoint = DEFAULT_ENDPOINT,
    fetchOptions,
}: BlockTreeProps): ReactElement {
    const blocks = normalizeTree(tree);

    return (
        <>
            {blocks.map((block, index) =>
                renderBlock(block, index, dynamicBlockEndpoint, fetchOptions)
            )}
        </>
    );
}

function renderBlock(
    block: Block,
    index: number,
    endpoint: string,
    fetchOptions: RequestInit | undefined
): ReactNode {
    const name = typeof block.name === 'string' ? block.name.trim() : '';

    if (name === '') {
        return null;
    }

    const attributes =
        block.attributes !== null && typeof block.attributes === 'object' && !Array.isArray(block.attributes)
            ? (block.attributes as Record<string, unknown>)
            : {};

    const innerBlocks = Array.isArray(block.innerBlocks) ? block.innerBlocks.filter(isBlock) : [];
    const key = typeof block.clientId === 'string' && block.clientId !== '' ? block.clientId : `${name}-${index}`;

    const renderedChildren =
        innerBlocks.length === 0
            ? null
            : innerBlocks.map((child, childIndex) =>
                  renderBlock(child, childIndex, endpoint, fetchOptions)
              );

    const Renderer = getBlockRenderer(name);

    if (Renderer !== undefined) {
        return (
            <Renderer
                key={key}
                name={name}
                attributes={attributes}
                innerBlocks={innerBlocks}
            >
                {renderedChildren === null ? null : <Fragment>{renderedChildren}</Fragment>}
            </Renderer>
        );
    }

    return (
        <DynamicBlock
            key={key}
            name={name}
            attributes={attributes}
            endpoint={endpoint}
            fetchOptions={fetchOptions}
        />
    );
}

function normalizeTree(tree: Block[] | string | null | undefined): Block[] {
    if (tree === null || tree === undefined) {
        return [];
    }

    if (typeof tree === 'string') {
        try {
            const parsed = JSON.parse(tree);

            return Array.isArray(parsed) ? parsed.filter(isBlock) : [];
        } catch {
            return [];
        }
    }

    return Array.isArray(tree) ? tree.filter(isBlock) : [];
}

function isBlock(value: unknown): value is Block {
    return (
        value !== null &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        typeof (value as { name?: unknown }).name === 'string'
    );
}

export { UnknownBlock };
