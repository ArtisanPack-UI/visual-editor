/**
 * List — saved markup.
 *
 * Ported from `@wordpress/block-library/src/list/save.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

interface ListSaveAttributes {
    readonly ordered?: boolean;
    readonly type?: string;
    readonly reversed?: boolean;
    readonly start?: number;
}

interface ListSaveProps {
    readonly attributes: ListSaveAttributes;
}

export default function ListSave({ attributes }: ListSaveProps): ReactElement {
    const { ordered, type, reversed, start } = attributes;
    const TagName = ordered ? 'ol' : 'ul';
    return (
        <TagName
            {...useBlockProps.save({
                reversed,
                start,
                style: {
                    listStyleType: ordered && type !== 'decimal' ? type : undefined,
                },
            })}
        >
            <InnerBlocks.Content />
        </TagName>
    );
}
