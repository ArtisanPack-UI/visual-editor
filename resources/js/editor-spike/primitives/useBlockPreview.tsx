import { useMemo, type CSSProperties, type ReactNode } from 'react';
import type { Block } from '../mocks/blockTree';
import { BlockTreeProvider } from './BlockTreeContext';
import { ReadOnlyProvider } from './ReadOnlyContext';
import { RenderBlock } from './useInnerBlocksProps';

export interface UseBlockPreviewOptions {
    blocks: Block[];
    viewportWidth?: number;
}

export interface BlockPreviewProps {
    children: ReactNode;
    style: CSSProperties;
    inert: boolean;
    'data-block-preview': true;
}

export interface UseBlockPreviewReturn {
    previewProps: BlockPreviewProps;
}

const previewStyle: CSSProperties = {
    pointerEvents: 'none',
    userSelect: 'none',
};

export function useBlockPreview({ blocks }: UseBlockPreviewOptions): UseBlockPreviewReturn {
    const children = useMemo<ReactNode>(
        () => (
            <BlockTreeProvider blocks={blocks}>
                <ReadOnlyProvider value={true}>
                    {blocks.map((block) => (
                        <RenderBlock key={block.clientId} block={block} />
                    ))}
                </ReadOnlyProvider>
            </BlockTreeProvider>
        ),
        [blocks]
    );

    return {
        previewProps: {
            children,
            style: previewStyle,
            inert: true,
            'data-block-preview': true,
        },
    };
}

export interface BlockPreviewComponentProps {
    blocks: Block[];
    viewportWidth?: number;
    className?: string;
}

export function BlockPreview({ blocks, viewportWidth, className }: BlockPreviewComponentProps) {
    const { previewProps } = useBlockPreview({ blocks, viewportWidth });

    return <div className={className} {...previewProps} />;
}
