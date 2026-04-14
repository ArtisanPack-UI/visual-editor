import { useState, type ReactNode } from 'react';
import { useStore } from 'zustand';
import type { Block } from '../store';
import { RenderBlock, useEditorStore } from '../primitives';

export interface BlockWrapperProps {
    block: Block;
    children?: ReactNode;
}

export function BlockWrapper({ block, children }: BlockWrapperProps) {
    const store = useEditorStore();
    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === block.clientId
    );
    const [isHovered, setIsHovered] = useState(false);

    const className = [
        've-block',
        isHovered ? 've-block--is-hovered' : null,
        isSelected ? 've-block--is-selected' : null,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div
            className={className}
            data-ve-block-wrapper=""
            data-ve-selected={isSelected || undefined}
            data-ve-hovered={isHovered || undefined}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div
                className="ve-block__drag-handle"
                data-ve-drag-handle-slot=""
                aria-hidden="true"
            />
            {children ?? <RenderBlock block={block} />}
        </div>
    );
}
