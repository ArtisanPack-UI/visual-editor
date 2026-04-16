import { type ChangeEvent, useCallback, useEffect } from 'react';
import { EditorContent } from '@tiptap/react';
import { useStore } from 'zustand';
import Blockquote from '@tiptap/extension-blockquote';
import Paragraph from '@tiptap/extension-paragraph';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';
import { useBlockTiptap } from '../shared/useBlockTiptap';
import { takePendingCursor } from '../shared/blockEditorRegistry';

export const QUOTE_BLOCK_NAME = 've/quote';

const QUOTE_EXTRA_EXTENSIONS = [Paragraph];

export default function QuoteEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const citation = typeof attributes.citation === 'string' ? attributes.citation : '';
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
        topLevelNode: Blockquote,
        docContentSpec: 'blockquote',
        onUpdate,
        onEnter: () => false,
        onBackspaceAtStart: () => false,
        extraExtensions: QUOTE_EXTRA_EXTENSIONS,
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

    const onCitationChange = useCallback(
        (event: ChangeEvent<HTMLInputElement>) => {
            store
                .getState()
                .updateBlockAttributes(clientId, { citation: event.target.value });
        },
        [store, clientId]
    );

    return (
        <div className="ve-block-quote-wrapper" data-block-name={QUOTE_BLOCK_NAME}>
            <EditorContent editor={editor} className="ve-block-quote" />
            <input
                type="text"
                className="ve-block-quote__citation"
                value={citation}
                placeholder="Add citation..."
                aria-label="Citation"
                data-testid="ve-quote-citation"
                onChange={onCitationChange}
            />
        </div>
    );
}
