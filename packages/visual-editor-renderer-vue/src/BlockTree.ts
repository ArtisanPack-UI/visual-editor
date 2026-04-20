/**
 * Public Vue component for rendering a saved block tree.
 *
 * Walks a `{ clientId, name, attributes, innerBlocks }` block tree the visual
 * editor persists and renders it to Vue VNodes. Each block name is looked up
 * in the shared block registry; anything unregistered falls through to the
 * {@link DynamicBlock} component, which fetches the server-rendered HTML from
 * the visual-editor preview endpoint.
 *
 * `tree` accepts the array form the editor persists, a JSON-encoded string of
 * that shape, or `null`/`undefined` for an empty render.
 */

import { Fragment, defineComponent, h } from 'vue';
import type { PropType, VNode } from 'vue';
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

export const BlockTree = defineComponent({
    name: 'BlockTree',
    props: {
        tree: {
            type: [Array, String, null] as unknown as PropType<Block[] | string | null | undefined>,
            default: null,
        },
        dynamicBlockEndpoint: {
            type: String,
            default: DEFAULT_ENDPOINT,
        },
        fetchOptions: {
            type: Object as PropType<RequestInit>,
            default: undefined,
        },
    },
    setup(props) {
        return () => {
            const blocks = normalizeTree(props.tree);
            const endpoint = props.dynamicBlockEndpoint ?? DEFAULT_ENDPOINT;
            const children = blocks
                .map((block, index) => renderBlock(block, index, endpoint, props.fetchOptions))
                .filter((vnode): vnode is VNode => vnode !== null);

            return h(Fragment, null, children);
        };
    },
});

function renderBlock(
    block: Block,
    index: number,
    endpoint: string,
    fetchOptions: RequestInit | undefined
): VNode | null {
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

    const renderedChildren = innerBlocks
        .map((child, childIndex) => renderBlock(child, childIndex, endpoint, fetchOptions))
        .filter((vnode): vnode is VNode => vnode !== null);

    const renderer = getBlockRenderer(name);

    if (renderer !== undefined) {
        const slots =
            renderedChildren.length === 0
                ? undefined
                : { default: () => renderedChildren };

        return h(
            renderer,
            {
                key,
                name,
                attributes,
                innerBlocks,
            },
            slots
        );
    }

    return h(DynamicBlock, {
        key,
        name,
        attributes,
        endpoint,
        fetchOptions,
    });
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
