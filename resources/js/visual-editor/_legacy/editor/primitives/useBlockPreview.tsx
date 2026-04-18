import { useMemo, type CSSProperties, type ReactNode } from 'react';
import { createEditorStore, type Block } from '../store';
import { EditorStoreProvider } from './EditorStoreContext';
import { ReadOnlyProvider } from './ReadOnlyContext';
import { RenderBlock } from './useInnerBlocksProps';

export interface UseBlockPreviewOptions {
    blocks: readonly Block[];
}

export interface BlockPreviewProps {
    children: ReactNode;
    style: CSSProperties;
    inert: true;
    'aria-hidden': 'true';
    'data-block-preview': true;
}

export interface UseBlockPreviewReturn {
    previewProps: BlockPreviewProps;
}

const PREVIEW_STYLE: CSSProperties = Object.freeze({
    pointerEvents: 'none',
    userSelect: 'none',
});

export function useBlockPreview({ blocks }: UseBlockPreviewOptions): UseBlockPreviewReturn {
    const children = useMemo<ReactNode>(() => {
        const previewStore = createEditorStore(blocks.slice());

        return (
            <EditorStoreProvider store={previewStore}>
                <ReadOnlyProvider value={true}>
                    {blocks.map((block) => (
                        <RenderBlock key={block.clientId} block={block} />
                    ))}
                </ReadOnlyProvider>
            </EditorStoreProvider>
        );
    }, [blocks]);

    return {
        previewProps: {
            children,
            style: PREVIEW_STYLE,
            inert: true,
            'aria-hidden': 'true',
            'data-block-preview': true,
        },
    };
}

export interface BlockPreviewComponentProps {
    blocks: readonly Block[];
    className?: string;
}

export function BlockPreview({ blocks, className }: BlockPreviewComponentProps) {
    const { previewProps } = useBlockPreview({ blocks });

    return <div className={className} {...previewProps} />;
}
