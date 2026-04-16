import {
    type ChangeEvent,
    type KeyboardEvent,
    useCallback,
    useEffect,
    useRef,
} from 'react';
import { useStore } from 'zustand';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';

export const CODE_BLOCK_NAME = 've/code';

export const CODE_LANGUAGES: readonly string[] = [
    'plaintext',
    'html',
    'css',
    'javascript',
    'typescript',
    'php',
    'python',
    'ruby',
    'go',
    'rust',
    'json',
    'yaml',
    'bash',
    'sql',
    'markdown',
];

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
            const textarea = event.currentTarget;
            const { selectionStart, selectionEnd, value } = textarea;
            const nextValue =
                value.slice(0, selectionStart) + '    ' + value.slice(selectionEnd);
            textarea.value = nextValue;
            textarea.selectionStart = textarea.selectionEnd = selectionStart + 4;
            store.getState().updateBlockAttributes(clientId, { content: nextValue });
        },
        [store, clientId]
    );

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
                data-testid="ve-code-textarea"
                data-language={language}
                onChange={onContentChange}
                onKeyDown={onKeyDown}
            />
        </div>
    );
}
