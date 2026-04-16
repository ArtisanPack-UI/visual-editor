import { useCallback, useEffect, useMemo } from 'react';
import { EditorContent, type Editor } from '@tiptap/react';
import { useStore } from 'zustand';
import Paragraph from '@tiptap/extension-paragraph';
import BulletList from '@tiptap/extension-bullet-list';
import OrderedList from '@tiptap/extension-ordered-list';
import ListItem from '@tiptap/extension-list-item';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';
import { useBlockTiptap } from '../shared/useBlockTiptap';
import { takePendingCursor } from '../shared/blockEditorRegistry';
import { createClientId, type Block } from '../../store';
import { PARAGRAPH_BLOCK_NAME } from '../paragraph';

export const LIST_BLOCK_NAME = 've/list';

export function normalizeOrdered(value: unknown): boolean {
    return value === true;
}

function wrapListContent(innerHtml: string, ordered: boolean): string {
    const tag = ordered ? 'ol' : 'ul';
    return `<${tag}>${innerHtml}</${tag}>`;
}

function extractListInner(html: string): string {
    const match = html.match(/^<(?:ul|ol)[^>]*>([\s\S]*)<\/(?:ul|ol)>$/);
    return match ? match[1] : html;
}

function ensureListContent(content: string, ordered: boolean): string {
    const trimmed = content.trim();
    if (trimmed.length === 0) {
        return wrapListContent('<li><p></p></li>', ordered);
    }
    const inner = extractListInner(trimmed);
    return wrapListContent(inner, ordered);
}

interface ResolvedPosLike {
    depth: number;
    node: (depth: number) => { type: { name: string } };
}

function findListItemDepth($pos: ResolvedPosLike): number | null {
    for (let depth = $pos.depth; depth > 0; depth--) {
        if ($pos.node(depth).type.name === 'listItem') {
            return depth;
        }
    }
    return null;
}

const LIST_EXTENSIONS = [OrderedList, ListItem, Paragraph];

export default function ListEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const ordered = normalizeOrdered(attributes.ordered);
    const content = useMemo(
        () => ensureListContent(
            typeof attributes.content === 'string' ? attributes.content : '',
            ordered
        ),
        [attributes.content, ordered]
    );
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

    const onEnter = useCallback((): boolean => {
        const activeEditor = editor;
        if (!activeEditor) {
            return false;
        }

        const { $from } = activeEditor.state.selection;
        const listItemDepth = findListItemDepth($from);

        if (listItemDepth === null) {
            return false;
        }

        const listItemNode = $from.node(listItemDepth);

        if (listItemNode.textContent.length > 0) {
            return false;
        }

        const state = store.getState();
        const index = state.blocks.findIndex((b) => b.clientId === clientId);

        if (index === -1) {
            return false;
        }

        const newBlock: Block = {
            clientId: createClientId(),
            name: PARAGRAPH_BLOCK_NAME,
            attributes: { content: '<p></p>' },
            innerBlocks: [],
        };

        const nextBlocks = state.blocks.slice();
        nextBlocks.splice(index + 1, 0, newBlock);
        state.replaceBlocks(nextBlocks);
        state.select(newBlock.clientId, 'start');

        return true;
        // `editor` is assigned below but referenced via closure; by the time
        // this callback runs it will be populated.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [store, clientId]);

    const editor: Editor | null = useBlockTiptap({
        clientId,
        content,
        topLevelNode: BulletList,
        docContentSpec: 'bulletList | orderedList',
        onUpdate,
        onEnter,
        onBackspaceAtStart: () => false,
        extraExtensions: LIST_EXTENSIONS,
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
        const expectedType = ordered ? 'orderedList' : 'bulletList';

        if (!first || first.type.name === expectedType) {
            return;
        }

        editor.commands.toggleList(expectedType, 'listItem');
    }, [editor, ordered]);

    return (
        <EditorContent
            editor={editor}
            data-block-name={LIST_BLOCK_NAME}
            data-list-ordered={ordered ? 'true' : 'false'}
            className="ve-block-list"
        />
    );
}
