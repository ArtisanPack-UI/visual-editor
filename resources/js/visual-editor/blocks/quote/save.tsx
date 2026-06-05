/**
 * Quote — saved markup.
 *
 * Ported from `@wordpress/block-library/src/quote/save.js` (v9.43.0).
 * Output is byte-equivalent to upstream `core/quote`.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';

interface QuoteSaveAttributes {
    readonly textAlign?: string;
    readonly citation?: string;
}

interface QuoteSaveProps {
    readonly attributes: QuoteSaveAttributes;
}

export default function QuoteSave({ attributes }: QuoteSaveProps): ReactElement {
    const { textAlign, citation } = attributes;

    const className = clsx({
        [`has-text-align-${textAlign}`]: textAlign,
    });

    return (
        <blockquote {...useBlockProps.save({ className })}>
            <InnerBlocks.Content />
            {!RichText.isEmpty(citation) && (
                <RichText.Content tagName="cite" value={citation} />
            )}
        </blockquote>
    );
}
