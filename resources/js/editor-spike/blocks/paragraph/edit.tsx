import { EditorContent } from '@tiptap/react';
import type { BlockEditProps } from '../../registry';
import { useReadOnly } from '../../primitives/ReadOnlyContext';
import { useTiptap } from '../../richtext/useTiptap';

export default function ParagraphEdit({ clientId, attributes, block }: BlockEditProps) {
    const readOnly = useReadOnly();
    const initialContent = typeof attributes.content === 'string' ? attributes.content : '';

    const editor = useTiptap({
        content: initialContent,
        editable: !readOnly,
        onUpdate: (html) => {
            block.attributes.content = html;
        },
    });

    if (readOnly) {
        return (
            <div
                data-client-id={clientId}
                data-block-name="ve/paragraph"
                data-read-only="true"
                dangerouslySetInnerHTML={{ __html: initialContent }}
            />
        );
    }

    return (
        <EditorContent
            editor={editor}
            data-client-id={clientId}
            data-block-name="ve/paragraph"
        />
    );
}
