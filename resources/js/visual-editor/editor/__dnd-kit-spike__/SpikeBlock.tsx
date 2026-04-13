import { type CSSProperties } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useTiptap } from '@spike/richtext/useTiptap';
import { EditorContent } from '@tiptap/react';

export interface SpikeBlockData {
    id: string;
    content: string;
}

interface SpikeBlockProps {
    block: SpikeBlockData;
    onChange: (id: string, html: string) => void;
}

export function SpikeBlock({ block, onChange }: SpikeBlockProps) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
        useSortable({ id: block.id });

    const editor = useTiptap({
        content: block.content,
        onUpdate: (html) => onChange(block.id, html),
    });

    const style: CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div ref={setNodeRef} style={style} className="spike-block" data-testid={`spike-block-${block.id}`}>
            <button
                type="button"
                className="spike-block__handle"
                aria-label={`Drag block ${block.id}`}
                data-testid={`spike-handle-${block.id}`}
                {...attributes}
                {...listeners}
            >
                ⠿
            </button>
            <div className="spike-block__body">
                <EditorContent editor={editor} />
            </div>
        </div>
    );
}
