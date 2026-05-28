/**
 * Verse — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/verse/edit.js` (v9.43.0).
 * Upstream `useDeprecatedTextAlign` is omitted — the deprecation chain
 * handles legacy `textAlign` migration on load.
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

interface VerseAttributes {
    readonly content: string;
}

interface VerseEditProps {
    readonly attributes: VerseAttributes;
    readonly setAttributes: (next: Partial<VerseAttributes>) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
    readonly onRemove?: () => void;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly style?: Record<string, unknown>;
}

export default function VerseEdit({
    attributes,
    setAttributes,
    mergeBlocks,
    onRemove,
    insertBlocksAfter,
    style,
}: VerseEditProps): ReactElement {
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
            aria-label={__('Poetry text')}
            placeholder={__('Write poetry…')}
            onRemove={onRemove}
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
