import { useCallback, useEffect, useMemo } from 'react';
import { EditorContent } from '@tiptap/react';
import { useStore } from 'zustand';
import Heading, { type Level } from '@tiptap/extension-heading';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';
import { useBlockTiptap } from '../shared/useBlockTiptap';
import { handleBlockBackspace, handleBlockEnter } from '../shared/blockSplitMerge';
import { takePendingCursor } from '../shared/blockEditorRegistry';

export const HEADING_BLOCK_NAME = 've/heading';
export const HEADING_LEVELS: readonly Level[] = [1, 2, 3, 4, 5, 6];

export function normalizeHeadingLevel(value: unknown): Level {
    if (typeof value === 'number' && HEADING_LEVELS.includes(value as Level)) {
        return value as Level;
    }
    return 2;
}

export default function HeadingEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const level = normalizeHeadingLevel(attributes.level);
    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === clientId
    );
    const selectionEdge = useStore(
        store,
        (state) => (state.selection.clientId === clientId ? state.selection.edge : undefined)
    );

    const headingNode = useMemo(
        () => Heading.configure({ levels: [...HEADING_LEVELS] }),
        []
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
        topLevelNode: headingNode,
        docContentSpec: 'heading',
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

    useEffect(() => {
        if (!editor) {
            return;
        }

        const first = editor.state.doc.firstChild;

        if (!first || first.type.name !== 'heading' || first.attrs.level === level) {
            return;
        }

        editor.chain().setNode('heading', { level }).run();
    }, [editor, level]);

    return (
        <EditorContent
            editor={editor}
            data-block-name={HEADING_BLOCK_NAME}
            data-heading-level={level}
            className="ve-block-heading"
        />
    );
}
