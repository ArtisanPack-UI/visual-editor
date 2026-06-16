/**
 * Columns — save component.
 *
 * Ported from `@wordpress/block-library/src/columns/save.js` (v9.43.0).
 * Adds an explicit `wp-block-columns` class so saved markup is
 * byte-equivalent regardless of namespace, plus flex serializer
 * classes for the new layout panel (#595).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

import {
    serializeFlex,
    type ArtisanpackFlexAttribute,
} from '../_shared/flex-controls';
import {
    getPhotoGridWrapperProps,
    type PhotoGridAttribute,
} from '../_shared/photo-grid';
import { BreakpointRegistry } from '../../responsive/registry';

interface ColumnsSaveAttributes {
    readonly verticalAlignment?: string;
    readonly isStackedOnMobile?: boolean;
    readonly artisanpackFlex?: ArtisanpackFlexAttribute | null;
    readonly photoGrid?: PhotoGridAttribute | null;
}

export default function columnsSave({
    attributes,
}: {
    attributes: ColumnsSaveAttributes;
}): ReactElement {
    const { isStackedOnMobile, verticalAlignment } = attributes;
    const flexResult = serializeFlex(
        attributes.artisanpackFlex ?? null,
        new BreakpointRegistry(),
    );
    const photoGridWrapper = getPhotoGridWrapperProps(attributes);
    const className = clsx(
        'wp-block-columns',
        {
            [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
            ['is-not-stacked-on-mobile']: !isStackedOnMobile,
        },
        flexResult.classes,
        photoGridWrapper.className,
    );

    const blockProps = (useBlockProps.save as any)({
        className,
        style: photoGridWrapper.style,
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);

    return <div {...innerBlocksProps} />;
}
