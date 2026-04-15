import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { EditorContent } from '@tiptap/react';
import { useStore } from 'zustand';
import Paragraph from '@tiptap/extension-paragraph';
import type { BlockEditProps } from '../../registry';
import { useEditorStore } from '../../primitives';
import { useBlockTiptap } from '../shared/useBlockTiptap';
import { handleBlockBackspace, handleBlockEnter } from '../shared/blockSplitMerge';
import { takePendingCursor } from '../shared/blockEditorRegistry';
import {
    filterInserterBlocks,
    getInserterBlocks,
    replaceBlockWithInserterBlock,
    subscribeInserterBlocks,
    type InserterBlock,
} from '../../inserter';
import { SlashCommandPopover } from '../../components/SlashCommandPopover';

export const PARAGRAPH_BLOCK_NAME = 've/paragraph';

const SLASH_PLAINTEXT_REGEX = /^\/(.*)$/;

function extractPlainText(html: string): string {
    if (typeof window === 'undefined') {
        return html;
    }

    const template = document.createElement('div');
    template.innerHTML = html;
    return template.textContent ?? '';
}

function detectSlashQuery(html: string): string | null {
    const text = extractPlainText(html);

    if (!text.startsWith('/')) {
        return null;
    }

    const match = text.match(SLASH_PLAINTEXT_REGEX);
    return match ? match[1] : null;
}

export default function ParagraphEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const isSelected = useStore(
        store,
        (state) => state.selection.clientId === clientId
    );
    const selectionEdge = useStore(
        store,
        (state) => (state.selection.clientId === clientId ? state.selection.edge : undefined)
    );

    const [slashQuery, setSlashQuery] = useState<string | null>(null);
    const [slashIndex, setSlashIndex] = useState(0);

    const inserterBlocks = useSyncExternalStore(
        subscribeInserterBlocks,
        getInserterBlocks,
        getInserterBlocks
    );

    const filteredSlashBlocks = useMemo(
        () => (slashQuery === null ? [] : filterInserterBlocks(inserterBlocks, slashQuery)),
        [slashQuery, inserterBlocks]
    );

    const slashActiveRef = useRef(false);
    const filteredRef = useRef<readonly InserterBlock[]>(filteredSlashBlocks);
    const slashIndexRef = useRef(0);

    slashActiveRef.current = slashQuery !== null;
    filteredRef.current = filteredSlashBlocks;
    slashIndexRef.current = slashIndex;

    const onUpdate = useCallback(
        (html: string) => {
            store.getState().updateBlockAttributes(clientId, { content: html });
            setSlashQuery(detectSlashQuery(html));
        },
        [store, clientId]
    );

    const selectSlashBlock = useCallback(
        (block: InserterBlock) => {
            replaceBlockWithInserterBlock(store, clientId, block.name);
            setSlashQuery(null);
        },
        [store, clientId]
    );

    const selectSlashBlockAtIndex = useCallback(
        (index: number) => {
            const block = filteredRef.current[index];
            if (block) {
                selectSlashBlock(block);
            }
        },
        [selectSlashBlock]
    );

    const editor = useBlockTiptap({
        clientId,
        content,
        topLevelNode: Paragraph,
        docContentSpec: 'paragraph',
        onUpdate,
        onEnter: () => {
            if (slashActiveRef.current) {
                if (filteredRef.current.length > 0) {
                    selectSlashBlockAtIndex(slashIndexRef.current);
                }
                return true;
            }
            if (!editor) {
                return false;
            }
            return handleBlockEnter(store, clientId, editor);
        },
        onBackspaceAtStart: () => {
            if (slashActiveRef.current) {
                return false;
            }
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
        setSlashIndex((current) => {
            if (filteredSlashBlocks.length === 0) {
                return 0;
            }
            return Math.min(current, filteredSlashBlocks.length - 1);
        });
    }, [filteredSlashBlocks]);

    useEffect(() => {
        if (!editor) {
            return;
        }

        const onBlur = (): void => {
            setSlashQuery(null);
        };

        editor.on('blur', onBlur);

        return () => {
            editor.off('blur', onBlur);
        };
    }, [editor]);

    useEffect(() => {
        if (slashQuery === null) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                event.stopPropagation();
                setSlashIndex((current) => {
                    if (filteredRef.current.length === 0) {
                        return 0;
                    }
                    return (current + 1) % filteredRef.current.length;
                });
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                event.stopPropagation();
                setSlashIndex((current) => {
                    if (filteredRef.current.length === 0) {
                        return 0;
                    }
                    return (current - 1 + filteredRef.current.length) % filteredRef.current.length;
                });
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setSlashQuery(null);
            }
        }

        window.addEventListener('keydown', onKeyDown, true);
        return () => window.removeEventListener('keydown', onKeyDown, true);
    }, [slashQuery]);

    return (
        <div className="ve-block-paragraph-wrapper">
            <EditorContent
                editor={editor}
                data-block-name={PARAGRAPH_BLOCK_NAME}
                className="ve-block-paragraph"
            />
            {slashQuery !== null ? (
                <SlashCommandPopover
                    blocks={filteredSlashBlocks}
                    selectedIndex={slashIndex}
                    onSelect={selectSlashBlock}
                    onHoverIndex={setSlashIndex}
                />
            ) : null}
        </div>
    );
}
