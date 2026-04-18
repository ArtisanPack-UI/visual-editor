import {
    useCallback,
    useMemo,
    useRef,
    type MouseEvent,
    type ReactNode,
    type RefObject,
} from 'react';
import { useStore } from 'zustand';
import type { Block, EditorStore } from '../store';
import { useChildren } from '../store';
import { getBlock as getBlockDefinition, type BlockDefinition } from '../registry';
import { BlockContextProvider } from './BlockContext';
import { useEditorStore } from './EditorStoreContext';

export interface InnerBlocksElementProps {
    ref: RefObject<HTMLDivElement | null>;
    children: ReactNode;
    className?: string;
    'data-parent-client-id': string;
    onClickCapture: (event: MouseEvent<HTMLDivElement>) => void;
}

export interface UseInnerBlocksPropsOptions {
    className?: string;
}

export interface UseInnerBlocksPropsReturn {
    innerBlocksProps: InnerBlocksElementProps;
    appendBlock: (block: Block, index?: number) => void;
    removeBlock: (clientId: string) => void;
    moveBlock: (clientId: string, index: number) => void;
}

const TOP_LEVEL_SENTINEL = '__ve_root__';

export function useInnerBlocksProps(
    parentClientId: string | null,
    options: UseInnerBlocksPropsOptions = {}
): UseInnerBlocksPropsReturn {
    const ref = useRef<HTMLDivElement>(null);
    const store = useEditorStore();
    const childBlocks = useChildren(store, parentClientId);

    const children = useMemo<ReactNode>(
        () =>
            childBlocks.map((block) => (
                <RenderBlock key={block.clientId} block={block} />
            )),
        [childBlocks]
    );

    const appendBlock = useCallback(
        (block: Block, index?: number) => {
            store.getState().insertBlock(block, { parentClientId, index });
        },
        [store, parentClientId]
    );

    const removeBlock = useCallback(
        (clientId: string) => {
            store.getState().removeBlock(clientId);
        },
        [store]
    );

    const moveBlock = useCallback(
        (clientId: string, index: number) => {
            store.getState().moveBlock(clientId, { parentClientId, index });
        },
        [store, parentClientId]
    );

    const onClickCapture = useCallback(
        (event: MouseEvent<HTMLDivElement>) => {
            const childClientId = findNearestBlockClientId(
                event.target as Element | null,
                ref.current
            );

            if (childClientId === null || childClientId === parentClientId) {
                return;
            }

            store.getState().select(childClientId);
        },
        [store, parentClientId]
    );

    return {
        innerBlocksProps: {
            ref,
            children,
            className: options.className,
            'data-parent-client-id': parentClientId ?? TOP_LEVEL_SENTINEL,
            onClickCapture,
        },
        appendBlock,
        removeBlock,
        moveBlock,
    };
}

interface RenderBlockProps {
    block: Block;
}

export function RenderBlock({ block }: RenderBlockProps) {
    const definition = getBlockDefinition(block.name);

    if (!definition) {
        return (
            <div data-block-missing={block.name} data-block-client-id={block.clientId}>
                Unknown block: {block.name}
            </div>
        );
    }

    return <BlockEditHost definition={definition} block={block} />;
}

interface BlockEditHostProps {
    definition: BlockDefinition;
    block: Block;
}

function BlockEditHost({ definition, block }: BlockEditHostProps) {
    const store = useEditorStore();
    const liveBlock = useLiveBlock(store, block.clientId) ?? block;

    const Edit = definition.edit;
    const editElement = (
        <Edit
            clientId={liveBlock.clientId}
            attributes={liveBlock.attributes}
            block={liveBlock}
        />
    );

    if (definition.providesContext) {
        const contextValue = definition.providesContext(liveBlock.attributes, liveBlock);
        return (
            <div data-block-client-id={liveBlock.clientId}>
                <BlockContextProvider value={contextValue}>{editElement}</BlockContextProvider>
            </div>
        );
    }

    return <div data-block-client-id={liveBlock.clientId}>{editElement}</div>;
}

function useLiveBlock(store: EditorStore, clientId: string): Block | undefined {
    return useStore(store, (state) => findBlockInState(state.blocks, clientId));
}

function findBlockInState(blocks: Block[], clientId: string): Block | undefined {
    for (const block of blocks) {
        if (block.clientId === clientId) {
            return block;
        }

        const found = findBlockInState(block.innerBlocks, clientId);

        if (found !== undefined) {
            return found;
        }
    }

    return undefined;
}

function findNearestBlockClientId(
    target: Element | null,
    boundary: HTMLElement | null
): string | null {
    let current: Element | null = target;

    while (current !== null) {
        if (boundary !== null && current === boundary) {
            return null;
        }

        if (current instanceof HTMLElement) {
            const id = current.dataset.blockClientId;

            if (typeof id === 'string' && id.length > 0) {
                return id;
            }
        }

        current = current.parentElement;
    }

    return null;
}

export interface InnerBlocksComponentProps {
    parentClientId: string | null;
    className?: string;
}

export function InnerBlocks({ parentClientId, className }: InnerBlocksComponentProps) {
    const { innerBlocksProps } = useInnerBlocksProps(parentClientId, { className });
    const { ref, children, ...rest } = innerBlocksProps;

    return (
        <div ref={ref} {...rest}>
            {children}
        </div>
    );
}
