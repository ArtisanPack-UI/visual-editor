import { useState, type CSSProperties, type ReactNode } from 'react';
import { useStore } from 'zustand';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
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

    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: block.clientId });

    const className = [
        've-block',
        isHovered ? 've-block--is-hovered' : null,
        isSelected ? 've-block--is-selected' : null,
        isDragging ? 've-block--is-dragging' : null,
    ]
        .filter(Boolean)
        .join(' ');

    const style: CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            className={className}
            style={style}
            data-ve-block-wrapper=""
            data-ve-selected={isSelected || undefined}
            data-ve-hovered={isHovered || undefined}
            data-ve-dragging={isDragging || undefined}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <button
                type="button"
                ref={setActivatorNodeRef}
                className="ve-block__drag-handle"
                data-ve-drag-handle-slot=""
                data-ve-drag-handle-for={block.clientId}
                aria-label={`Drag block ${block.name}`}
                {...attributes}
                {...listeners}
            >
                <span aria-hidden="true">⠿</span>
            </button>
            {children ?? <RenderBlock block={block} />}
        </div>
    );
}
