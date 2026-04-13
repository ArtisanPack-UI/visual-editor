import { useEffect } from 'react';
import { useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';

export interface UseTiptapOptions {
    content: string;
    editable?: boolean;
    onUpdate?: (html: string, editor: Editor) => void;
}

const editorsByDom = new WeakMap<HTMLElement, Editor>();

export function getTiptapEditor(domNode: HTMLElement): Editor | null {
    return editorsByDom.get(domNode) ?? null;
}

export function useTiptap({
    content,
    editable = true,
    onUpdate,
}: UseTiptapOptions): Editor | null {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: false,
                bulletList: false,
                orderedList: false,
                listItem: false,
                blockquote: false,
                codeBlock: false,
                horizontalRule: false,
                link: false,
            }),
            Link.configure({
                openOnClick: false,
                autolink: true,
            }),
        ],
        content,
        editable,
        editorProps: {
            attributes: {
                class: 've-richtext',
            },
        },
        onUpdate: ({ editor: activeEditor }) => {
            onUpdate?.(activeEditor.getHTML(), activeEditor);
        },
        immediatelyRender: false,
    });

    useEffect(() => {
        if (!editor) {
            return;
        }

        const dom = editor.view.dom as HTMLElement;
        editorsByDom.set(dom, editor);

        return () => {
            editorsByDom.delete(dom);
        };
    }, [editor]);

    return editor;
}
