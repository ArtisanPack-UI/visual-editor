/**
 * List item — saved markup.
 *
 * Ported from `@wordpress/block-library/src/list-item/save.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';

interface ListItemSaveAttributes {
    readonly content: string;
}

interface ListItemSaveProps {
    readonly attributes: ListItemSaveAttributes;
}

export default function ListItemSave({
    attributes,
}: ListItemSaveProps): ReactElement {
    return (
        <li {...useBlockProps.save()}>
            <RichText.Content value={attributes.content} />
            <InnerBlocks.Content />
        </li>
    );
}
