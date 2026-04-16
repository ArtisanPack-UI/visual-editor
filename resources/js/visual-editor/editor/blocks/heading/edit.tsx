import { useCallback } from 'react';
import type { Level } from '@tiptap/extension-heading';
import type { BlockEditProps } from '../../registry';
import { useEditorStore, RichText } from '../../primitives';
import { handleBlockBackspace, handleBlockEnter } from '../shared/blockSplitMerge';
import { getBlockEditor } from '../shared/blockEditorRegistry';

import metadata from './block.json';

export const HEADING_BLOCK_NAME = metadata.name;
export const HEADING_LEVELS: readonly Level[] = [1, 2, 3, 4, 5, 6];

export function normalizeHeadingLevel(value: unknown): Level {
    if (typeof value === 'number' && HEADING_LEVELS.includes(value as Level)) {
        return value as Level;
    }
    return 2;
}

export default function HeadingEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const level = normalizeHeadingLevel(attributes.level);

    const onChange = useCallback(
        (html: string) => {
            store.getState().updateBlockAttributes(clientId, { content: html });
        },
        [store, clientId]
    );

    const onEnter = useCallback((): boolean => {
        const editor = getBlockEditor(clientId);
        if (!editor) {
            return false;
        }
        return handleBlockEnter(store, clientId, editor);
    }, [store, clientId]);

    const onBackspaceAtStart = useCallback((): boolean => {
        const editor = getBlockEditor(clientId);
        if (!editor) {
            return false;
        }
        return handleBlockBackspace(store, clientId, editor);
    }, [store, clientId]);

    return (
        <RichText
            clientId={clientId}
            tagName={`h${level}`}
            value={content}
            onChange={onChange}
            onEnter={onEnter}
            onBackspaceAtStart={onBackspaceAtStart}
            blockName={HEADING_BLOCK_NAME}
            className="ve-block-heading"
        />
    );
}
