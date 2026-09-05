/**
 * Table of Contents — saved markup.
 *
 * The list itself is derived server-side by `TocResolver`, so the saved
 * markup only records the block's configuration (heading label, min/max
 * levels, ordered flag). The Blade renderer walks the resolved tree and
 * builds the nested list at request time (#760); React and Vue
 * renderers emit an empty landmark because the tree they consume does
 * not include cross-block state.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface TocAttributes {
    readonly heading: string;
    readonly headingLevel: number;
    readonly minLevel: number;
    readonly maxLevel: number;
    readonly ordered: boolean;
}

interface TocSaveProps {
    readonly attributes: TocAttributes;
}

function clampLabelLevel(value: unknown): 2 | 3 | 4 | 5 | 6 {
    const numeric = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(numeric)) {
        return 2;
    }

    const rounded = Math.round(numeric);

    if (rounded <= 2) {
        return 2;
    }

    if (rounded >= 6) {
        return 6;
    }

    return rounded as 2 | 3 | 4 | 5 | 6;
}

export default function TocSave({ attributes }: TocSaveProps): ReactElement {
    const { heading, headingLevel } = attributes;

    const blockProps = useBlockProps.save({ className: 'ap-toc' });

    const headingTag = `h${clampLabelLevel(headingLevel)}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <nav {...blockProps}>
            {heading !== '' && (
                <RichText.Content
                    tagName={headingTag}
                    className="ap-toc__heading"
                    value={heading}
                />
            )}
        </nav>
    );
}
