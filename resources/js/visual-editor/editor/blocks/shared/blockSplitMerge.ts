import type { Editor } from '@tiptap/react';
import type { Block, EditorStore } from '../../store';
import { createClientId } from '../../store';
import { splitAtCursor, isCursorAtStart } from './splitContent';
import { getBlockEditor, setPendingCursor } from './blockEditorRegistry';
import { blockSupports } from '../../registry';
import { PARAGRAPH_BLOCK_NAME } from '../paragraph';

function findTopLevelIndex(blocks: Block[], clientId: string): number {
    return blocks.findIndex((block) => block.clientId === clientId);
}

/**
 * Handle an Enter keypress inside a text block. Splits the current block's
 * content at the cursor into two halves, mutates the current editor to the
 * left half, and atomically replaces the block tree so a single history
 * entry captures both the attribute update and the inserted new block.
 */
export function handleBlockEnter(
    store: EditorStore,
    clientId: string,
    editor: Editor
): boolean {
    const state = store.getState();
    const topLevelIndex = findTopLevelIndex(state.blocks, clientId);

    if (topLevelIndex === -1) {
        return false;
    }

    const { leftHtml, rightHtml } = splitAtCursor(editor);
    const currentBlock = state.blocks[topLevelIndex];

    const newBlock: Block = {
        clientId: createClientId(),
        name: PARAGRAPH_BLOCK_NAME,
        attributes: { content: rightHtml },
        innerBlocks: [],
    };

    const nextBlocks = state.blocks.slice();
    nextBlocks[topLevelIndex] = {
        ...currentBlock,
        attributes: { ...currentBlock.attributes, content: leftHtml },
    };
    nextBlocks.splice(topLevelIndex + 1, 0, newBlock);

    editor.commands.setContent(leftHtml, { emitUpdate: false });

    state.replaceBlocks(nextBlocks);
    state.select(newBlock.clientId, 'start');

    return true;
}

/**
 * Handle Backspace-at-start inside a text block. When the previous sibling
 * is another text block, merges the current block's inline content into the
 * end of the previous block and atomically replaces the block tree so undo
 * reverses the merge in a single step. Places the cursor on the previous
 * editor at the merge boundary (end of the original previous text).
 */
export function handleBlockBackspace(
    store: EditorStore,
    clientId: string,
    editor: Editor
): boolean {
    if (!isCursorAtStart(editor)) {
        return false;
    }

    const state = store.getState();
    const topLevelIndex = findTopLevelIndex(state.blocks, clientId);

    if (topLevelIndex <= 0) {
        return false;
    }

    const previousBlock = state.blocks[topLevelIndex - 1];

    if (!blockSupports(previousBlock.name, 'splitting')) {
        return false;
    }

    const previousEditor = getBlockEditor(previousBlock.clientId);

    if (!previousEditor) {
        return false;
    }

    const previousFirst = previousEditor.state.doc.firstChild;
    const previousTextLength = previousFirst?.textContent.length ?? 0;
    // ProseMirror positions: 1 is just inside the top-level node's opening
    // boundary, so the end of the existing previous text sits at 1 + len.
    const mergePos = previousTextLength + 1;

    const currentInner = extractInnerHtml(editor.getHTML());
    const previousHtml = typeof previousBlock.attributes.content === 'string'
        ? previousBlock.attributes.content
        : previousEditor.getHTML();
    const previousInner = extractInnerHtml(previousHtml);
    const mergedInner = previousInner + currentInner;
    const mergedHtml = wrapInSameTag(previousHtml, mergedInner);

    previousEditor.commands.setContent(mergedHtml, { emitUpdate: false });

    const nextBlocks = state.blocks.slice();
    nextBlocks[topLevelIndex - 1] = {
        ...previousBlock,
        attributes: { ...previousBlock.attributes, content: mergedHtml },
    };
    nextBlocks.splice(topLevelIndex, 1);

    setPendingCursor(previousBlock.clientId, mergePos);
    state.replaceBlocks(nextBlocks);
    state.select(previousBlock.clientId);

    return true;
}

function extractInnerHtml(outerHtml: string): string {
    const match = outerHtml.match(/^<[^>]+>([\s\S]*)<\/[^>]+>$/);
    return match ? match[1] : outerHtml;
}

function wrapInSameTag(templateHtml: string, innerHtml: string): string {
    const openMatch = templateHtml.match(/^(<[^>]+>)/);
    const closeMatch = templateHtml.match(/(<\/[^>]+>)$/);

    if (!openMatch || !closeMatch) {
        return innerHtml;
    }

    return `${openMatch[1]}${innerHtml}${closeMatch[1]}`;
}
