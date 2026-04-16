import { useCallback, useEffect } from 'react';
import { EditorContent } from '@tiptap/react';
import { Node } from '@tiptap/core';
import { useStore } from 'zustand';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';
import { useBlockTiptap } from '../shared/useBlockTiptap';
import { takePendingCursor } from '../shared/blockEditorRegistry';

export const PREFORMATTED_BLOCK_NAME = 've/preformatted';

export const PreformattedNode = Node.create({
    name: 'preformatted',
    group: 'block',
    content: 'inline*',
    marks: 'bold italic link',
    defining: true,
    code: true,
    parseHTML() {
        return [{ tag: 'pre' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['pre', HTMLAttributes, 0];
    },
});

export default function PreformattedEdit({ clientId, attributes }: BlockEditProps) {
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
        topLevelNode: PreformattedNode,
        docContentSpec: 'preformatted',
        onUpdate,
        onEnter: () => false,
        onBackspaceAtStart: () => false,
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
            data-block-name={PREFORMATTED_BLOCK_NAME}
            className="ve-block-preformatted"
        />
    );
}
