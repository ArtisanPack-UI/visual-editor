/**
 * Preformatted — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/preformatted/edit.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

interface PreformattedAttributes {
    readonly content: string;
}

interface PreformattedEditProps {
    readonly attributes: PreformattedAttributes;
    readonly setAttributes: (next: Partial<PreformattedAttributes>) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
    readonly onRemove?: () => void;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly style?: Record<string, unknown>;
}

export default function PreformattedEdit({
    attributes,
    mergeBlocks,
    setAttributes,
    onRemove,
    insertBlocksAfter,
    style,
}: PreformattedEditProps): ReactElement {
    const { content } = attributes;
    const blockProps = useBlockProps({ style });

    return (
        <RichText
            tagName="pre"
            identifier="content"
            preserveWhiteSpace
            value={content}
            onChange={(nextContent: string) =>
                setAttributes({ content: nextContent })
            }
            onRemove={onRemove}
            aria-label={__('Preformatted text')}
            placeholder={__('Write preformatted text…')}
            onMerge={mergeBlocks}
            {...blockProps}
            __unstablePastePlainText
            __unstableOnSplitAtDoubleLineEnd={() => {
                const defaultName = getDefaultBlockName();
                if (defaultName) {
                    insertBlocksAfter?.(createBlock(defaultName));
                }
            }}
        />
    );
}
