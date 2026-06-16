/**
 * Group — save component.
 *
 * Ported from `@wordpress/block-library/src/group/save.js` (v9.43.0).
 * Adds an explicit `wp-block-group` class to `useBlockProps.save()` so
 * the saved markup matches the editor regardless of namespace, and
 * appends the flex layout serializer's classes so frontend rendering
 * mirrors the editor.
 */

import type { ReactElement } from 'react';
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';
import clsx from 'clsx';

import {
    serializeFlex,
    type ArtisanpackFlexAttribute,
} from '../_shared/flex-controls';
import {
    getPhotoGridWrapperProps,
    type PhotoGridAttribute,
} from '../_shared/photo-grid';
import { BreakpointRegistry } from '../../responsive/registry';

interface GroupSaveAttributes {
    readonly tagName?: string;
    readonly artisanpackFlex?: ArtisanpackFlexAttribute | null;
    readonly photoGrid?: PhotoGridAttribute | null;
}

export default function groupSave({
    attributes,
}: {
    attributes: GroupSaveAttributes;
}): ReactElement {
    const Tag = (attributes.tagName ?? 'div') as keyof JSX.IntrinsicElements;
    const flexResult = serializeFlex(
        attributes.artisanpackFlex ?? null,
        new BreakpointRegistry(),
    );
    const photoGridWrapper = getPhotoGridWrapperProps(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className: clsx(
            'wp-block-group',
            flexResult.classes,
            photoGridWrapper.className,
        ),
        style: photoGridWrapper.style,
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <Tag {...innerBlocksProps} />;
}
