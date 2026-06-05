/**
 * Columns — save component.
 *
 * Ported from `@wordpress/block-library/src/columns/save.js` (v9.43.0).
 * Adds an explicit `wp-block-columns` class so saved markup is
 * byte-equivalent regardless of namespace.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

interface ColumnsSaveAttributes {
    readonly verticalAlignment?: string;
    readonly isStackedOnMobile?: boolean;
}

export default function columnsSave({
    attributes,
}: {
    attributes: ColumnsSaveAttributes;
}): ReactElement {
    const { isStackedOnMobile, verticalAlignment } = attributes;
    const className = clsx('wp-block-columns', {
        [`are-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        ['is-not-stacked-on-mobile']: !isStackedOnMobile,
    });

    const blockProps = (useBlockProps.save as any)({ className });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);

    return <div {...innerBlocksProps} />;
}
