/**
 * Group — save component.
 *
 * Ported from `@wordpress/block-library/src/group/save.js` (v9.43.0).
 * Adds an explicit `wp-block-group` class to `useBlockProps.save()` so
 * the saved markup matches the editor regardless of namespace.
 */

import type { ReactElement } from 'react';
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

interface GroupSaveAttributes {
    readonly tagName?: string;
}

export default function groupSave({
    attributes,
}: {
    attributes: GroupSaveAttributes;
}): ReactElement {
    const Tag = (attributes.tagName ?? 'div') as keyof JSX.IntrinsicElements;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className: 'wp-block-group',
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <Tag {...innerBlocksProps} />;
}
