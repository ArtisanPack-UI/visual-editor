import { useCallback, useMemo, useState, type MouseEvent } from 'react';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type Announcements,
    type DragEndEvent,
    type DragStartEvent,
    type ScreenReaderInstructions,
    type UniqueIdentifier,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { useChildren, createClientId, type Block } from '../store';
import { useEditorStore, useInnerBlocksProps, RenderBlock } from '../primitives';
import { BlockWrapper } from './BlockWrapper';
import { PARAGRAPH_BLOCK_NAME } from '../blocks/paragraph';

export interface BlockListProps {
    className?: string;
}

const screenReaderInstructions: ScreenReaderInstructions = {
    draggable: [
        'To pick up a block, press space or enter.',
        'While dragging, use the arrow keys to move the block up or down.',
        'Press space or enter again to drop the block, or press escape to cancel.',
    ].join(' '),
};

export function BlockList({ className }: BlockListProps) {
    const store = useEditorStore();
    const topLevelBlocks = useChildren(store, null);
    const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);

    const { innerBlocksProps } = useInnerBlocksProps(null, {
        className: ['ve-block-list', className].filter(Boolean).join(' '),
    });
    const {
        ref,
        className: mergedClassName,
        'data-parent-client-id': parentClientId,
        onClickCapture,
    } = innerBlocksProps;

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 8 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const sortableIds = useMemo(
        () => topLevelBlocks.map((block) => block.clientId),
        [topLevelBlocks]
    );

    const announcements = useMemo<Announcements>(() => {
        const describe = (id: UniqueIdentifier): string => {
            const index = topLevelBlocks.findIndex((block) => block.clientId === id);
            const position = index === -1 ? 0 : index + 1;
            return `block ${position} of ${topLevelBlocks.length}`;
        };

        return {
            onDragStart: ({ active }) => `Picked up ${describe(active.id)}.`,
            onDragOver: ({ active, over }) =>
                over
                    ? `${describe(active.id)} was moved over ${describe(over.id)}.`
                    : `${describe(active.id)} is no longer over a droppable area.`,
            onDragEnd: ({ active, over }) =>
                over
                    ? `${describe(active.id)} was dropped over ${describe(over.id)}.`
                    : `${describe(active.id)} was dropped.`,
            onDragCancel: ({ active }) =>
                `Dragging was cancelled. ${describe(active.id)} was returned to its original position.`,
        };
    }, [topLevelBlocks]);

    const handleDragStart = useCallback((event: DragStartEvent) => {
        setActiveId(event.active.id);
    }, []);

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveId(null);

            const { active, over } = event;

            if (!over || active.id === over.id) {
                return;
            }

            const activeClientId = String(active.id);
            const overClientId = String(over.id);

            const currentBlocks = store.getState().blocks;
            const oldIndex = currentBlocks.findIndex(
                (block) => block.clientId === activeClientId
            );
            const newIndex = currentBlocks.findIndex(
                (block) => block.clientId === overClientId
            );

            if (oldIndex === -1 || newIndex === -1) {
                return;
            }

            store.getState().moveBlock(activeClientId, {
                parentClientId: null,
                index: newIndex,
            });
        },
        [store]
    );

    const handleDragCancel = useCallback(() => {
        setActiveId(null);
    }, []);

    const activeBlock = useMemo(
        () =>
            activeId === null
                ? null
                : topLevelBlocks.find((block) => block.clientId === activeId) ?? null,
        [activeId, topLevelBlocks]
    );

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            accessibility={{ announcements, screenReaderInstructions }}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={handleDragCancel}
        >
            <SortableContext items={sortableIds} strategy={verticalListSortingStrategy}>
                <div
                    ref={ref}
                    className={mergedClassName}
                    data-parent-client-id={parentClientId}
                    onClickCapture={onClickCapture}
                >
                    <BetweenBlockInserter index={0} />
                    {topLevelBlocks.map((block, index) => (
                        <div key={block.clientId}>
                            <BlockWrapper block={block} />
                            <BetweenBlockInserter index={index + 1} />
                        </div>
                    ))}
                </div>
            </SortableContext>
            <DragOverlay>
                {activeBlock ? (
                    <div
                        className="ve-block ve-block--drag-overlay"
                        data-ve-drag-overlay=""
                    >
                        <RenderBlock block={activeBlock} />
                    </div>
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}

function BetweenBlockInserter({ index }: { index: number }) {
    const store = useEditorStore();

    const onClick = useCallback(
        (event: MouseEvent) => {
            event.stopPropagation();
            const newBlock: Block = {
                clientId: createClientId(),
                name: PARAGRAPH_BLOCK_NAME,
                attributes: { content: '<p></p>' },
                innerBlocks: [],
            };
            store.getState().insertBlock(newBlock, { index });
            store.getState().select(newBlock.clientId, 'start');
        },
        [store, index]
    );

    return (
        <div className="ve-between-block-inserter">
            <button
                type="button"
                className="ve-between-block-inserter__button"
                onClick={onClick}
                aria-label={`Insert block at position ${index + 1}`}
                data-testid={`ve-between-inserter-${index}`}
            >
                <span className="ve-between-block-inserter__line" />
                <span className="ve-between-block-inserter__icon">+</span>
                <span className="ve-between-block-inserter__line" />
            </button>
        </div>
    );
}
