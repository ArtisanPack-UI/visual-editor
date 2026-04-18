import { useCallback } from 'react';
import { Node } from '@tiptap/core';
import type { BlockEditProps } from '../../registry';
import { useEditorStore, RichText } from '../../primitives';

import metadata from './block.json';

export const PREFORMATTED_BLOCK_NAME = metadata.name;

export const PreformattedNode = Node.create({
    name: 'preformatted',
    group: 'block',
    content: 'inline*',
    marks: 'bold italic link',
    defining: true,
    code: true,
    parseHTML() {
        return [{ tag: 'pre' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['pre', HTMLAttributes, 0];
    },
});

const PRE_EXTENSIONS = [PreformattedNode];

export default function PreformattedEdit({ clientId, attributes }: BlockEditProps) {
    const store = useEditorStore();
    const content = typeof attributes.content === 'string' ? attributes.content : '';

    const onChange = useCallback(
        (html: string) => {
            store.getState().updateBlockAttributes(clientId, { content: html });
        },
        [store, clientId]
    );

    return (
        <RichText
            clientId={clientId}
            tagName="preformatted"
            value={content}
            onChange={onChange}
            blockName={PREFORMATTED_BLOCK_NAME}
            className="ve-block-preformatted"
            extraExtensions={PRE_EXTENSIONS}
        />
    );
}
