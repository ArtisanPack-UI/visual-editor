import { useEffect, useRef } from 'react';
import { useEditor, type Editor } from '@tiptap/react';
import type { Extensions } from '@tiptap/core';
import Document from '@tiptap/extension-document';
import Text from '@tiptap/extension-text';
import HardBreak from '@tiptap/extension-hard-break';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import Link from '@tiptap/extension-link';
import {
    registerBlockEditor,
    unregisterBlockEditor,
} from './blockEditorRegistry';

export interface UseBlockTiptapOptions {
    clientId: string;
    content: string;
    editable?: boolean;
    topLevelNode: Extensions[number];
    docContentSpec: string;
    onUpdate: (html: string) => void;
    onEnter: () => boolean;
    onBackspaceAtStart: () => boolean;
    extraExtensions?: Extensions;
}

/**
 * Shared Tiptap factory for Phase 1 block edit components. Configures a
 * minimal editor with a single top-level node (paragraph or heading), inline
 * marks (bold/italic/link), and a block-level keymap that routes Enter and
 * Backspace back to the Zustand store.
 */
export function useBlockTiptap({
    clientId,
    content,
    editable = true,
    topLevelNode,
    docContentSpec,
    onUpdate,
    onEnter,
    onBackspaceAtStart,
    extraExtensions,
}: UseBlockTiptapOptions): Editor | null {
    const lastEmittedHtmlRef = useRef<string>(content);
    const onUpdateRef = useRef(onUpdate);
    const onEnterRef = useRef(onEnter);
    const onBackspaceRef = useRef(onBackspaceAtStart);

    onUpdateRef.current = onUpdate;
    onEnterRef.current = onEnter;
    onBackspaceRef.current = onBackspaceAtStart;

    const editor = useEditor({
        extensions: [
            Document.extend({ content: docContentSpec }),
            topLevelNode,
            Text,
            HardBreak,
            Bold,
            Italic,
            Link.configure({
                openOnClick: false,
                autolink: false,
                HTMLAttributes: { rel: 'noopener noreferrer' },
            }),
            ...(extraExtensions ?? []),
        ],
        content,
        editable,
        editorProps: {
            attributes: {
                class: 've-richtext',
            },
            handleKeyDown: (_view, event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    const handled = onEnterRef.current();
                    if (handled) {
                        event.preventDefault();
                    }
                    return handled;
                }
                if (event.key === 'Backspace') {
                    const handled = onBackspaceRef.current();
                    if (handled) {
                        event.preventDefault();
                    }
                    return handled;
                }
                return false;
            },
        },
        onUpdate: ({ editor: activeEditor }) => {
            const html = activeEditor.getHTML();
            lastEmittedHtmlRef.current = html;
            onUpdateRef.current(html);
        },
        immediatelyRender: false,
    });

    useEffect(() => {
        if (!editor) {
            return;
        }

        registerBlockEditor(clientId, editor);

        return () => {
            unregisterBlockEditor(clientId);
        };
    }, [editor, clientId]);

    useEffect(() => {
        if (!editor) {
            return;
        }

        if (content === lastEmittedHtmlRef.current) {
            return;
        }

        if (editor.getHTML() === content) {
            lastEmittedHtmlRef.current = content;
            return;
        }

        editor.commands.setContent(content, { emitUpdate: false });
        lastEmittedHtmlRef.current = content;
    }, [editor, content]);

    return editor;
}
