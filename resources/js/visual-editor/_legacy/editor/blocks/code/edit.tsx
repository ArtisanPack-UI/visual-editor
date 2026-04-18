import {
    type ChangeEvent,
    type KeyboardEvent,
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
} from 'react';
import { useStore } from 'zustand';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';

import metadata from './block.json';

export const CODE_BLOCK_NAME = metadata.name;

export const CODE_LANGUAGES: readonly string[] =
    (metadata.attributes?.language?.enum as string[] | undefined) ?? ['plaintext'];

export function normalizeLanguage(value: unknown): string {
    if (typeof value === 'string' && CODE_LANGUAGES.includes(value)) {
        return value;
    }
    return 'plaintext';
}

export default function CodeEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const language = normalizeLanguage(attributes.language);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const desiredCaretRef = useRef<number | null>(null);

    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === clientId
    );

    const onContentChange = useCallback(
        (event: ChangeEvent<HTMLTextAreaElement>) => {
            store
                .getState()
                .updateBlockAttributes(clientId, { content: event.target.value });
        },
        [store, clientId]
    );

    const onLanguageChange = useCallback(
        (event: ChangeEvent<HTMLSelectElement>) => {
            store
                .getState()
                .updateBlockAttributes(clientId, { language: event.target.value });
        },
        [store, clientId]
    );

    const onKeyDown = useCallback(
        (event: KeyboardEvent<HTMLTextAreaElement>) => {
            if (event.key !== 'Tab') {
                return;
            }
            event.preventDefault();
            const { selectionStart, selectionEnd, value } = event.currentTarget;
            const nextValue =
                value.slice(0, selectionStart) + '    ' + value.slice(selectionEnd);
            desiredCaretRef.current = selectionStart + 4;
            store.getState().updateBlockAttributes(clientId, { content: nextValue });
        },
        [store, clientId]
    );

    // Restore caret position after controlled value update from Tab indent
    useLayoutEffect(() => {
        if (desiredCaretRef.current === null || !textareaRef.current) {
            return;
        }
        const pos = desiredCaretRef.current;
        desiredCaretRef.current = null;
        textareaRef.current.selectionStart = pos;
        textareaRef.current.selectionEnd = pos;
    }, [content]);

    useEffect(() => {
        if (!isSelected || !textareaRef.current) {
            return;
        }
        if (document.activeElement === textareaRef.current) {
            return;
        }
        textareaRef.current.focus();
    }, [isSelected]);

    return (
        <div className="ve-block-code-wrapper" data-block-name={CODE_BLOCK_NAME}>
            <label className="ve-block-code__language">
                <span className="ve-sr-only">Language</span>
                <select
                    value={language}
                    aria-label="Code language"
                    data-testid="ve-code-language"
                    onChange={onLanguageChange}
                >
                    {CODE_LANGUAGES.map((lang) => (
                        <option key={lang} value={lang}>
                            {lang}
                        </option>
                    ))}
                </select>
            </label>
            <textarea
                ref={textareaRef}
                className="ve-block-code__textarea"
                value={content}
                spellCheck={false}
                aria-label={`Code editor (${language})`}
                data-testid="ve-code-textarea"
                data-language={language}
                onChange={onContentChange}
                onKeyDown={onKeyDown}
            />
        </div>
    );
}
