/**
 * Preformatted — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/preformatted/edit.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import type { BlockInstance } from '@wordpress/blocks';

import { useExitOnDoubleEnter } from '../_shared/use-exit-on-double-enter';

interface PreformattedAttributes {
    readonly content: string;
}

interface PreformattedEditProps {
    readonly attributes: PreformattedAttributes;
    readonly setAttributes: (next: Partial<PreformattedAttributes>) => void;
    readonly clientId: string;
    readonly mergeBlocks?: (forward?: boolean) => void;
    readonly onRemove?: () => void;
    readonly insertBlocksAfter?: (blocks: BlockInstance | BlockInstance[]) => void;
    readonly style?: Record<string, unknown>;
}

export default function PreformattedEdit({
    attributes,
    mergeBlocks,
    setAttributes,
    onRemove,
    insertBlocksAfter,
    style,
    clientId,
}: PreformattedEditProps): ReactElement {
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
            onRemove={onRemove}
            aria-label={__('Preformatted text', TEXT_DOMAIN)}
            placeholder={__('Write preformatted text…', TEXT_DOMAIN)}
            onMerge={mergeBlocks}
            {...blockProps}
            __unstablePastePlainText
        />
    );
}
