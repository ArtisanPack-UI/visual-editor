import { useCallback, useState } from 'react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { SpikeBlock, type SpikeBlockData } from './SpikeBlock';

const INITIAL_BLOCKS: SpikeBlockData[] = [
    { id: 'block-a', content: '<p>Alpha — edit me, then drag.</p>' },
    { id: 'block-b', content: '<p>Bravo — selecting text should not trigger drag.</p>' },
    { id: 'block-c', content: '<p>Charlie — state must persist through reorders.</p>' },
    { id: 'block-d', content: '<p>Delta — keyboard drag should not hijack typing.</p>' },
];

export function DndKitSpike() {
    const [blocks, setBlocks] = useState<SpikeBlockData[]>(INITIAL_BLOCKS);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 8 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const handleChange = useCallback((id: string, html: string) => {
        setBlocks((current) =>
            current.map((block) => (block.id === id ? { ...block, content: html } : block))
        );
    }, []);

    const handleDragEnd = useCallback((event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setBlocks((current) => {
            const oldIndex = current.findIndex((block) => block.id === active.id);
            const newIndex = current.findIndex((block) => block.id === over.id);

            if (oldIndex === -1 || newIndex === -1) {
                return current;
            }

            return arrayMove(current, oldIndex, newIndex);
        });
    }, []);

    return (
        <div className="spike-root" data-testid="dnd-kit-spike">
            <header className="spike-header">
                <h1>dnd-kit + Tiptap spike harness</h1>
                <p>
                    Drag via the handle on the left of each block. Text selection inside
                    blocks should remain untouched. Keyboard drag: Tab to a handle, press
                    Space to pick up, arrow keys to move, Space to drop.
                </p>
            </header>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext items={blocks} strategy={verticalListSortingStrategy}>
                    <ol className="spike-list" data-testid="spike-list">
                        {blocks.map((block) => (
                            <li key={block.id}>
                                <SpikeBlock block={block} onChange={handleChange} />
                            </li>
                        ))}
                    </ol>
                </SortableContext>
            </DndContext>
        </div>
    );
}
