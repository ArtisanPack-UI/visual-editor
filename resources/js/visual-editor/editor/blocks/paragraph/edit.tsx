import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import type { BlockEditProps } from '../../registry';
import { useEditorStore, RichText } from '../../primitives';
import { handleBlockBackspace, handleBlockEnter } from '../shared/blockSplitMerge';
import { getBlockEditor } from '../shared/blockEditorRegistry';
import {
    filterInserterBlocks,
    getInserterBlocks,
    replaceBlockWithInserterBlock,
    subscribeInserterBlocks,
    type InserterBlock,
} from '../../inserter';
import { SlashCommandPopover } from '../../components/SlashCommandPopover';

import metadata from './block.json';

export const PARAGRAPH_BLOCK_NAME = metadata.name;

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

    const onChange = useCallback(
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

    const onEnter = useCallback((): boolean => {
        if (slashActiveRef.current) {
            if (filteredRef.current.length > 0) {
                selectSlashBlockAtIndex(slashIndexRef.current);
            }
            return true;
        }
        const editor = getBlockEditor(clientId);
        if (!editor) {
            return false;
        }
        return handleBlockEnter(store, clientId, editor);
    }, [store, clientId, selectSlashBlockAtIndex]);

    const onBackspaceAtStart = useCallback((): boolean => {
        if (slashActiveRef.current) {
            return false;
        }
        const editor = getBlockEditor(clientId);
        if (!editor) {
            return false;
        }
        return handleBlockBackspace(store, clientId, editor);
    }, [store, clientId]);

    useEffect(() => {
        setSlashIndex((current) => {
            if (filteredSlashBlocks.length === 0) {
                return 0;
            }
            return Math.min(current, filteredSlashBlocks.length - 1);
        });
    }, [filteredSlashBlocks]);

    useEffect(() => {
        const editor = getBlockEditor(clientId);
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
    }, [clientId]);

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
            <RichText
                clientId={clientId}
                tagName="p"
                value={content}
                onChange={onChange}
                onEnter={onEnter}
                onBackspaceAtStart={onBackspaceAtStart}
                blockName={PARAGRAPH_BLOCK_NAME}
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
