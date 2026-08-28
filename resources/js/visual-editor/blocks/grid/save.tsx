/**
 * Grid — save component.
 *
 * Dynamic block: the server-side renderer walks the persisted inner
 * tree. But the block hosts `grid-item` children inside a wrapper
 * `<div>` (see `edit.tsx`), so `save()` must reproduce that wrapper —
 * mirroring the editor's `useBlockProps` className/style and the masonry
 * `data-ap-cols` hook — or Gutenberg's validator flags the saved markup
 * as "unexpected or invalid content" (#747).
 *
 * Legacy content that predates the `ap-grid*` layout classes (serialized
 * with only the auto `wp-block-artisanpack-grid` wrapper class) validates
 * and migrates through `./deprecated`.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import {
    getPhotoGridWrapperProps,
    type PhotoGridAttribute,
} from '../_shared/photo-grid';

type GridLayoutMode = 'fixed' | 'masonry';

interface GridSaveAttributes {
    readonly numColumns: number;
    readonly layoutMode?: GridLayoutMode;
    readonly photoGrid?: PhotoGridAttribute | null;
}

function normalizeLayoutMode(value: unknown): GridLayoutMode {
    return 'masonry' === value ? 'masonry' : 'fixed';
}

function clampColumns(value: number | undefined, fallback: number): number {
    const next =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : fallback;
    if (next < 1) {
        return 1;
    }
    if (next > 12) {
        return 12;
    }
    return next;
}

export default function GridSave({
    attributes,
}: {
    attributes: GridSaveAttributes;
}): ReactElement {
    const numColumns = clampColumns(attributes.numColumns, 4);
    const isMasonry = 'masonry' === normalizeLayoutMode(attributes.layoutMode);
    const photoGridWrapper = getPhotoGridWrapperProps(attributes);
    const className = [
        'ap-grid',
        `ap-grid-has-${numColumns}-base-columns`,
        isMasonry ? 'ap-grid-layout-masonry' : 'ap-grid-layout-fixed',
        photoGridWrapper.className,
    ]
        .filter(Boolean)
        .join(' ');

    const blockProps = (useBlockProps.save as any)({
        className,
        style: photoGridWrapper.style,
    });
    const outerProps: Record<string, unknown> = { ...blockProps };
    if (isMasonry) {
        outerProps['data-ap-cols'] = numColumns;
    }
    const innerBlocksProps = (useInnerBlocksProps.save as any)(outerProps);
    return <div {...innerBlocksProps} />;
}
