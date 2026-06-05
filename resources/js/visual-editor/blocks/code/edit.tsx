/**
 * Code — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/code/edit.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

interface CodeAttributes {
    readonly content: string;
}

interface CodeEditProps {
    readonly attributes: CodeAttributes;
    readonly setAttributes: (next: Partial<CodeAttributes>) => void;
    readonly onRemove?: () => void;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
}

export default function CodeEdit({
    attributes,
    setAttributes,
    onRemove,
    insertBlocksAfter,
    mergeBlocks,
}: CodeEditProps): ReactElement {
    const blockProps = useBlockProps();
    return (
        <pre {...blockProps}>
            <RichText
                tagName="code"
                identifier="content"
                value={attributes.content}
                onChange={(content: string) => setAttributes({ content })}
                onRemove={onRemove}
                onMerge={mergeBlocks}
                placeholder={__('Write code…')}
                aria-label={__('Code')}
                preserveWhiteSpace
                __unstablePastePlainText
                __unstableOnSplitAtDoubleLineEnd={() => {
                    const defaultName = getDefaultBlockName();
                    if (defaultName) {
                        insertBlocksAfter?.(createBlock(defaultName));
                    }
                }}
            />
        </pre>
    );
}
