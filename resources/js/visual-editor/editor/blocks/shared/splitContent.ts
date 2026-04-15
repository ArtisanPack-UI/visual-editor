import type { Editor } from '@tiptap/react';
import { DOMSerializer } from '@tiptap/pm/model';

export type SplitResult = {
    leftHtml: string;
    rightHtml: string;
};

/**
 * Splits the editor's single top-level node at the current cursor position.
 * Returns HTML for the "left" half (before cursor) and the "right" half
 * (from cursor to end), each serialized as the same node type the editor
 * currently uses (paragraph, heading, etc.).
 */
export function splitAtCursor(editor: Editor): SplitResult {
    const { state } = editor;
    const { from } = state.selection;
    const topNode = state.doc.firstChild;

    if (!topNode) {
        return { leftHtml: '', rightHtml: '' };
    }

    const docSize = state.doc.content.size;
    const serializer = DOMSerializer.fromSchema(state.schema);

    const leftSlice = state.doc.slice(1, from);
    const rightSlice = state.doc.slice(from, docSize - 1);

    const leftNode = topNode.type.create(topNode.attrs, leftSlice.content);
    const rightNode = topNode.type.create(topNode.attrs, rightSlice.content);

    const leftDom = serializer.serializeNode(leftNode) as HTMLElement;
    const rightDom = serializer.serializeNode(rightNode) as HTMLElement;

    return {
        leftHtml: leftDom.outerHTML,
        rightHtml: rightDom.outerHTML,
    };
}

export function isCursorAtStart(editor: Editor): boolean {
    const { from, to } = editor.state.selection;
    return from === to && from <= 1;
}

export function isCursorAtEnd(editor: Editor): boolean {
    const { from, to } = editor.state.selection;
    return from === to && from >= editor.state.doc.content.size - 1;
}
