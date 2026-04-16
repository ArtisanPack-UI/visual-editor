import { useCallback, useMemo, useState } from 'react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
    type UniqueIdentifier,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useStore } from 'zustand';
import { faGripVertical } from '@fortawesome/free-solid-svg-icons';
import { getBlock } from '../registry';
import { useChildren, type Block } from '../store';
import { useEditorStore } from '../primitives';
import { Icon } from './Icon';

export function LayersPanel() {
    const store = useEditorStore();
    const topLevelBlocks = useChildren(store, null);
    const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const sortableIds = useMemo(
        () => topLevelBlocks.map((b) => b.clientId),
        [topLevelBlocks]
    );

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveId(null);
            const { active, over } = event;

            if (!over || active.id === over.id) {
                return;
            }

            const currentBlocks = store.getState().blocks;
            const overIndex = currentBlocks.findIndex(
                (b) => b.clientId === String(over.id)
            );

            if (overIndex === -1) {
                return;
            }

            store.getState().moveBlock(String(active.id), {
                parentClientId: null,
                index: overIndex,
            });
        },
        [store]
    );

    return (
        <div className="ve-layers-panel" data-testid="ve-layers-panel">
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragStart={(e) => setActiveId(e.active.id)}
                onDragEnd={handleDragEnd}
                onDragCancel={() => setActiveId(null)}
            >
                <SortableContext items={sortableIds} strategy={verticalListSortingStrategy}>
                    <ul className="ve-layers-panel__list" role="list" aria-label="Block layers">
                        {topLevelBlocks.map((block) => (
                            <SortableLayerItem
                                key={block.clientId}
                                block={block}
                                depth={0}
                                isDragActive={activeId !== null}
                            />
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>
            {topLevelBlocks.length === 0 ? (
                <p className="ve-layers-panel__empty">No blocks yet.</p>
            ) : null}
        </div>
    );
}

interface SortableLayerItemProps {
    block: Block;
    depth: number;
    isDragActive: boolean;
}

function SortableLayerItem({ block, depth, isDragActive }: SortableLayerItemProps) {
    const store = useEditorStore();
    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === block.clientId
    );

    const {
        attributes,
        listeners,
        setNodeRef,
        setActivatorNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: block.clientId });

    const blockDef = getBlock(block.name);
    const title = blockDef?.title ?? block.name;

    const onClick = useCallback(() => {
        store.getState().select(block.clientId);
    }, [store, block.clientId]);

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    };

    return (
        <li
            ref={setNodeRef}
            style={style}
            className={[
                've-layers-panel__item',
                isSelected ? 've-layers-panel__item--selected' : null,
                isDragging ? 've-layers-panel__item--dragging' : null,
            ].filter(Boolean).join(' ')}
            {...attributes}
            role="listitem"
            data-testid={`ve-layer-${block.clientId}`}
        >
            <div className="ve-layers-panel__item-row">
                <span
                    ref={setActivatorNodeRef}
                    className="ve-layers-panel__item-grip"
                    tabIndex={0}
                    role="button"
                    aria-label={`Drag ${title}`}
                    {...listeners}
                >
                    <Icon icon={faGripVertical} />
                </span>
                <button
                    type="button"
                    className="ve-layers-panel__item-button"
                    onClick={onClick}
                    aria-current={isSelected || undefined}
                    style={{ paddingLeft: `${depth * 16}px` }}
                >
                    <span className="ve-layers-panel__item-title">{title}</span>
                </button>
            </div>
            {block.innerBlocks.length > 0 && !isDragActive ? (
                <ul className="ve-layers-panel__children" role="list">
                    {block.innerBlocks.map((child) => (
                        <ChildLayerItem
                            key={child.clientId}
                            block={child}
                            depth={depth + 1}
                        />
                    ))}
                </ul>
            ) : null}
        </li>
    );
}

interface ChildLayerItemProps {
    block: Block;
    depth: number;
}

function ChildLayerItem({ block, depth }: ChildLayerItemProps) {
    const store = useEditorStore();
    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === block.clientId
    );

    const blockDef = getBlock(block.name);
    const title = blockDef?.title ?? block.name;

    const onClick = useCallback(() => {
        store.getState().select(block.clientId);
    }, [store, block.clientId]);

    return (
        <li
            className={[
                've-layers-panel__item',
                isSelected ? 've-layers-panel__item--selected' : null,
            ].filter(Boolean).join(' ')}
            role="listitem"
            data-testid={`ve-layer-${block.clientId}`}
        >
            <div className="ve-layers-panel__item-row">
                <button
                    type="button"
                    className="ve-layers-panel__item-button"
                    onClick={onClick}
                    aria-current={isSelected || undefined}
                    style={{ paddingLeft: `${depth * 16 + 24}px` }}
                >
                    <span className="ve-layers-panel__item-title">{title}</span>
                </button>
            </div>
            {block.innerBlocks.length > 0 ? (
                <ul className="ve-layers-panel__children" role="list">
                    {block.innerBlocks.map((child) => (
                        <ChildLayerItem
                            key={child.clientId}
                            block={child}
                            depth={depth + 1}
                        />
                    ))}
                </ul>
            ) : null}
        </li>
    );
}
