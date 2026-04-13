import { useMemo, useRef, type ReactNode, type RefObject } from 'react';
import type { Block } from '../mocks/blockTree';
import { getBlock, type BlockDefinition } from '../registry';
import { BlockContextProvider } from './BlockContext';
import { useBlockTree } from './BlockTreeContext';

export interface UseInnerBlocksPropsOptions {
    className?: string;
}

export interface InnerBlocksProps {
    ref: RefObject<HTMLDivElement | null>;
    children: ReactNode;
    className?: string;
    'data-parent-client-id': string;
}

export function useInnerBlocksProps(
    parentClientId: string,
    options: UseInnerBlocksPropsOptions = {}
): InnerBlocksProps {
    const ref = useRef<HTMLDivElement>(null);
    const { getChildren } = useBlockTree();

    const children = useMemo(() => {
        const childBlocks = getChildren(parentClientId);
        return childBlocks.map((block) => <RenderBlock key={block.clientId} block={block} />);
    }, [parentClientId, getChildren]);

    return {
        ref,
        children,
        className: options.className,
        'data-parent-client-id': parentClientId,
    };
}

interface RenderBlockProps {
    block: Block;
}

function RenderBlock({ block }: RenderBlockProps) {
    const definition = getBlock(block.name);

    if (!definition) {
        return (
            <div data-block-missing={block.name} data-client-id={block.clientId}>
                Unknown block: {block.name}
            </div>
        );
    }

    return renderBlockEdit(definition, block);
}

function renderBlockEdit(definition: BlockDefinition, block: Block): ReactNode {
    const Edit = definition.edit;
    const editElement = (
        <Edit clientId={block.clientId} attributes={block.attributes} block={block} />
    );

    if (definition.providesContext) {
        const contextValue = definition.providesContext(block.attributes, block);
        return <BlockContextProvider value={contextValue}>{editElement}</BlockContextProvider>;
    }

    return editElement;
}

export interface InnerBlocksComponentProps {
    parentClientId: string;
    className?: string;
}

export function InnerBlocks({ parentClientId, className }: InnerBlocksComponentProps) {
    const { ref, children, ...rest } = useInnerBlocksProps(parentClientId, { className });

    return (
        <div ref={ref} {...rest}>
            {children}
        </div>
    );
}
