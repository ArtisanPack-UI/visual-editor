import { useEffect, useMemo } from 'react';
import { EditorContent } from '@tiptap/react';
import { useStore } from 'zustand';
import type { Extensions } from '@tiptap/core';
import Paragraph from '@tiptap/extension-paragraph';
import Heading from '@tiptap/extension-heading';
import { useBlockTiptap } from '../blocks/shared/useBlockTiptap';
import { takePendingCursor } from '../blocks/shared/blockEditorRegistry';
import { useEditorStore } from './EditorStoreContext';

// ---------------------------------------------------------------------------
// Tag → Tiptap node mapping
// ---------------------------------------------------------------------------

const HEADING_TAG_REGEX = /^h([1-6])$/i;

function resolveTopLevelNode(tagName: string, extraExtensions?: Extensions): {
    node: Extensions[number];
    docContentSpec: string;
} {
    const headingMatch = tagName.match(HEADING_TAG_REGEX);

    if (headingMatch) {
        return {
            node: Heading.configure({ levels: [1, 2, 3, 4, 5, 6] }),
            docContentSpec: 'heading',
        };
    }

    if (tagName === 'p' || tagName === 'paragraph') {
        return { node: Paragraph, docContentSpec: 'paragraph' };
    }

    // For unknown tags, use paragraph as fallback — the extra extensions
    // should supply the actual top-level node.
    if (extraExtensions && extraExtensions.length > 0) {
        const ext = extraExtensions[0];
        const extName = typeof ext === 'object' && ext !== null && 'name' in ext
            ? (ext as { name: string }).name
            : tagName;
        return { node: ext, docContentSpec: extName };
    }

    return { node: Paragraph, docContentSpec: 'paragraph' };
}

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface RichTextProps {
    /** Block clientId — used for store selection tracking and editor registry. */
    clientId: string;
    /** HTML tag name that determines the Tiptap top-level node. */
    tagName: string;
    /** Current HTML content. */
    value: string;
    /** Fires when the editor content changes. */
    onChange: (html: string) => void;
    /** Placeholder text shown when the editor is empty. */
    placeholder?: string;
    /** CSS class applied to the wrapper element. */
    className?: string;
    /** data-block-name attribute for the wrapper. */
    blockName?: string;
    /**
     * Called when Enter is pressed (without Shift). Return `true` to
     * prevent default Tiptap behavior (e.g. for block splitting).
     */
    onEnter?: () => boolean;
    /**
     * Called when Backspace is pressed at the start of the content.
     * Return `true` to prevent default behavior (e.g. for block merging).
     */
    onBackspaceAtStart?: () => boolean;
    /** Additional Tiptap extensions beyond the base set. */
    extraExtensions?: Extensions;
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

/**
 * `<RichText>` is the primary rich-text editing primitive for block edit
 * components. It wraps a Tiptap editor instance, handles focus/selection
 * sync with the Zustand store, and provides a clean API for block-level
 * keyboard behaviors (Enter to split, Backspace to merge).
 *
 * @example
 * ```tsx
 * <RichText
 *     clientId={clientId}
 *     tagName="p"
 *     value={content}
 *     onChange={(html) => updateAttributes({ content: html })}
 * />
 * ```
 */
export function RichText({
    clientId,
    tagName,
    value,
    onChange,
    placeholder,
    className,
    blockName,
    onEnter,
    onBackspaceAtStart,
    extraExtensions,
}: RichTextProps) {
    const store = useEditorStore();

    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === clientId
    );
    const selectionEdge = useStore(
        store,
        (state) => (state.selection.clientId === clientId ? state.selection.edge : undefined)
    );

    const { node, docContentSpec } = useMemo(
        () => resolveTopLevelNode(tagName, extraExtensions),
        [tagName, extraExtensions]
    );

    const editor = useBlockTiptap({
        clientId,
        content: value,
        topLevelNode: node,
        docContentSpec,
        onUpdate: onChange,
        onEnter: onEnter ?? (() => false),
        onBackspaceAtStart: onBackspaceAtStart ?? (() => false),
        extraExtensions: extraExtensions?.slice(1),
        placeholder,
    });

    // Heading level sync — when tagName changes (e.g. h2 → h4),
    // update the Tiptap node type without recreating the editor.
    useEffect(() => {
        if (!editor) {
            return;
        }

        const headingMatch = tagName.match(HEADING_TAG_REGEX);

        if (!headingMatch) {
            return;
        }

        const targetLevel = Number(headingMatch[1]);
        const first = editor.state.doc.firstChild;

        if (!first || first.type.name !== 'heading' || first.attrs.level === targetLevel) {
            return;
        }

        editor.chain().setNode('heading', { level: targetLevel }).run();
    }, [editor, tagName]);

    // Focus management — centralized here so individual block edit
    // components don't need to duplicate this logic.
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
            className={className}
            data-block-name={blockName}
            data-placeholder={placeholder}
        />
    );
}
