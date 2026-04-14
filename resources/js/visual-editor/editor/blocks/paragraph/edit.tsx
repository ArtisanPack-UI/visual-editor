import { useCallback, useEffect } from 'react';
import { EditorContent } from '@tiptap/react';
import { useStore } from 'zustand';
import Paragraph from '@tiptap/extension-paragraph';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';
import { useBlockTiptap } from '../shared/useBlockTiptap';
import { handleBlockBackspace, handleBlockEnter } from '../shared/blockSplitMerge';
import { takePendingCursor } from '../shared/blockEditorRegistry';

export const PARAGRAPH_BLOCK_NAME = 've/paragraph';

export default function ParagraphEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === clientId
    );
    const selectionEdge = useStore(
        store,
        (state) => (state.selection.clientId === clientId ? state.selection.edge : undefined)
    );

    const onUpdate = useCallback(
        (html: string) => {
            store.getState().updateBlockAttributes(clientId, { content: html });
        },
        [store, clientId]
    );

    const editor = useBlockTiptap({
        clientId,
        content,
        topLevelNode: Paragraph,
        docContentSpec: 'paragraph',
        onUpdate,
        onEnter: () => {
            if (!editor) {
                return false;
            }
            return handleBlockEnter(store, clientId, editor);
        },
        onBackspaceAtStart: () => {
            if (!editor) {
                return false;
            }
            return handleBlockBackspace(store, clientId, editor);
        },
    });

    useEffect(() => {
        if (!editor || !isSelected) {
            return;
        }

        const pending = takePendingCursor(clientId);

        if (pending !== undefined) {
            editor.commands.focus(pending);
            return;
        }

        if (editor.isFocused) {
            return;
        }

        const position = selectionEdge === 'start' ? 'start' : 'end';
        editor.commands.focus(position);
    }, [editor, isSelected, selectionEdge, clientId]);

    return (
        <EditorContent
            editor={editor}
            data-block-name={PARAGRAPH_BLOCK_NAME}
            className="ve-block-paragraph"
        />
    );
}
