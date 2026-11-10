/**
 * Verse — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/verse/edit.js` (v9.43.0).
 * Upstream `useDeprecatedTextAlign` is omitted — the deprecation chain
 * handles legacy `textAlign` migration on load.
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import type { BlockInstance } from '@wordpress/blocks';

import { useExitOnDoubleEnter } from '../_shared/use-exit-on-double-enter';

interface VerseAttributes {
    readonly content: string;
}

interface VerseEditProps {
    readonly attributes: VerseAttributes;
    readonly setAttributes: (next: Partial<VerseAttributes>) => void;
    readonly clientId: string;
    readonly mergeBlocks?: (forward?: boolean) => void;
    readonly onRemove?: () => void;
    readonly insertBlocksAfter?: (blocks: BlockInstance | BlockInstance[]) => void;
    readonly style?: Record<string, unknown>;
}

export default function VerseEdit({
    attributes,
    setAttributes,
    mergeBlocks,
    onRemove,
    insertBlocksAfter,
    style,
    clientId,
}: VerseEditProps): ReactElement {
    const { content } = attributes;
    const exitOnDoubleEnterRef = useExitOnDoubleEnter({
        clientId,
        setAttributes: setAttributes as (next: Record<string, unknown>) => void,
        insertBlocksAfter,
    });
    const blockProps = useBlockProps({ style, ref: exitOnDoubleEnterRef });

    return (
        <RichText
            tagName="pre"
            identifier="content"
            preserveWhiteSpace
            value={content}
            onChange={(nextContent: string) =>
                setAttributes({ content: nextContent })
            }
            aria-label={__('Poetry text', TEXT_DOMAIN)}
            placeholder={__('Write poetry…', TEXT_DOMAIN)}
            onRemove={onRemove}
            onMerge={mergeBlocks}
            {...blockProps}
            __unstablePastePlainText
        />
    );
}
