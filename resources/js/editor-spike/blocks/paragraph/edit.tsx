import { EditorContent } from '@tiptap/react';
import type { BlockEditProps } from '../../registry';
import { useBlockContextValue } from '../../primitives/BlockContext';
import { useReadOnly } from '../../primitives/ReadOnlyContext';
import { useTiptap } from '../../richtext/useTiptap';

export default function ParagraphEdit({ clientId, attributes, block }: BlockEditProps) {
    const readOnly = useReadOnly();
    const bumpTemplate = useBlockContextValue<() => void>('__bumpTemplate');
    const initialContent = typeof attributes.content === 'string' ? attributes.content : '';

    const editor = useTiptap({
        content: initialContent,
        editable: !readOnly,
        onUpdate: (html) => {
            block.attributes.content = html;
            bumpTemplate?.();
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
