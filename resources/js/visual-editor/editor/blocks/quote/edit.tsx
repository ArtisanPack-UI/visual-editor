import { type ChangeEvent, useCallback } from 'react';
import Blockquote from '@tiptap/extension-blockquote';
import Paragraph from '@tiptap/extension-paragraph';
import type { BlockEditProps } from '../../registry';
import { useEditorStore, RichText } from '../../primitives';

import metadata from './block.json';

export const QUOTE_BLOCK_NAME = metadata.name;

const QUOTE_EXTRA_EXTENSIONS = [Blockquote, Paragraph];

export default function QuoteEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';
    const citation = typeof attributes.citation === 'string' ? attributes.citation : '';

    const onChange = useCallback(
        (html: string) => {
            store.getState().updateBlockAttributes(clientId, { content: html });
        },
        [store, clientId]
    );

    const onCitationChange = useCallback(
        (event: ChangeEvent<HTMLInputElement>) => {
            store
                .getState()
                .updateBlockAttributes(clientId, { citation: event.target.value });
        },
        [store, clientId]
    );

    return (
        <div className="ve-block-quote-wrapper" data-block-name={QUOTE_BLOCK_NAME}>
            <RichText
                clientId={clientId}
                tagName="blockquote"
                value={content}
                onChange={onChange}
                className="ve-block-quote"
                extraExtensions={QUOTE_EXTRA_EXTENSIONS}
            />
            <input
                type="text"
                className="ve-block-quote__citation"
                value={citation}
                placeholder="Add citation..."
                aria-label="Citation"
                data-testid="ve-quote-citation"
                onChange={onCitationChange}
            />
        </div>
    );
}
