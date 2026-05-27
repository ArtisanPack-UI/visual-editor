/**
 * Paragraph — migrate legacy `align` to `style.typography.textAlign`.
 *
 * Ported from `@wordpress/block-library/src/paragraph/deprecated-attributes.js`.
 * Plugins authored before Gutenberg 7.0 may still pass `align` directly to
 * `core/paragraph`; we mirror the upstream migration so forked saved markup
 * stays byte-equivalent.
 */

import { useEffect, useRef } from '@wordpress/element';
import { useEvent } from '@wordpress/compose';
import deprecated from '@wordpress/deprecated';
import { useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

interface ParagraphStyle {
    readonly typography?: {
        readonly textAlign?: string;
    } & Record<string, unknown>;
}

type SetAttributesFn = (attrs: Record<string, unknown>) => void;

export default function useDeprecatedAlign(
    align: string | undefined,
    style: ParagraphStyle | undefined,
    setAttributes: SetAttributesFn,
): void {
    const { __unstableMarkNextChangeAsNotPersistent } =
        useDispatch(blockEditorStore);

    const updateStyleWithAlign = useEvent(() => {
        deprecated('align attribute in paragraph block', {
            alternative: 'style.typography.textAlign',
            since: '7.0',
        });
        __unstableMarkNextChangeAsNotPersistent();
        setAttributes({
            style: {
                ...style,
                typography: {
                    ...style?.typography,
                    textAlign: align,
                },
            },
        });
    });

    const lastUpdatedAlignRef = useRef<string | undefined>();
    useEffect(() => {
        if (
            align === 'full' ||
            align === 'wide' ||
            align === lastUpdatedAlignRef.current
        ) {
            return;
        }
        lastUpdatedAlignRef.current = align;
        updateStyleWithAlign();
    }, [align, updateStyleWithAlign]);
}
