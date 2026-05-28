/**
 * Paragraph — saved markup.
 *
 * Ported from `@wordpress/block-library/src/paragraph/save.js` (v9.43.0).
 * The serialized HTML matches upstream byte-for-byte so that documents
 * authored against `core/paragraph` round-trip losslessly through the
 * `core/paragraph` ↔ `artisanpack/paragraph` transforms.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { isRTL } from '@wordpress/i18n';

interface ParagraphStyle {
    readonly typography?: {
        readonly textAlign?: string;
    } & Record<string, unknown>;
}

interface ParagraphSaveAttributes {
    readonly content: string;
    readonly direction?: 'ltr' | 'rtl';
    readonly dropCap: boolean;
    readonly style?: ParagraphStyle;
}

interface ParagraphSaveProps {
    readonly attributes: ParagraphSaveAttributes;
}

export default function ParagraphSave({ attributes }: ParagraphSaveProps): ReactElement {
    const { content, dropCap, direction, style } = attributes;
    const textAlign = style?.typography?.textAlign;
    const effectiveIsRtl = direction ? direction === 'rtl' : isRTL();
    const className = clsx('wp-block-paragraph', {
        'has-drop-cap':
            textAlign === (effectiveIsRtl ? 'left' : 'right') ||
            textAlign === 'center'
                ? false
                : dropCap,
    });

    return (
        <p {...useBlockProps.save({ className, dir: direction })}>
            <RichText.Content value={content} />
        </p>
    );
}
