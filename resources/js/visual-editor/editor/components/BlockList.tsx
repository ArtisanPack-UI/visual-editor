import { useChildren } from '../store';
import { useEditorStore, useInnerBlocksProps } from '../primitives';
import { BlockWrapper } from './BlockWrapper';

export interface BlockListProps {
    className?: string;
}

export function BlockList({ className }: BlockListProps) {
    const store = useEditorStore();
    const topLevelBlocks = useChildren(store, null);
    const { innerBlocksProps } = useInnerBlocksProps(null, {
        className: ['ve-block-list', className].filter(Boolean).join(' '),
    });
    const {
        ref,
        className: mergedClassName,
        'data-parent-client-id': parentClientId,
        onClickCapture,
    } = innerBlocksProps;

    return (
        <div
            ref={ref}
            className={mergedClassName}
            data-parent-client-id={parentClientId}
            onClickCapture={onClickCapture}
        >
            {topLevelBlocks.map((block) => (
                <BlockWrapper key={block.clientId} block={block} />
            ))}
        </div>
    );
}
