/**
 * Grid Item — save component.
 *
 * Dynamic block: renderers walk the persisted inner-block tree. But the
 * block hosts inner blocks inside a wrapper `<div>` (see `edit.tsx`), so
 * `save()` must reproduce that wrapper — mirroring the editor's
 * `useBlockProps` className (layout + span + flex classes) — or
 * Gutenberg's validator flags the saved markup as "unexpected or invalid
 * content" (#747).
 *
 * The column-span max is clamped to 12 here (rather than the parent
 * grid's resolved `numColumns`, which is only available via block
 * context in the editor): persisted spans are already clamped to the
 * parent's column count on input, so a fixed 12 ceiling reproduces the
 * same class for any valid stored value.
 *
 * Legacy content that predates the `ap-grid-item*` classes (serialized
 * with only the auto `wp-block-artisanpack-grid-item` wrapper class)
 * validates and migrates through `./deprecated`.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import {
    serializeFlex,
    type ArtisanpackFlexAttribute,
} from '../_shared/flex-controls';
import { BreakpointRegistry } from '../../responsive/registry';

type InnerLayout = 'normal' | 'equal' | 'center' | 'bottom' | 'last-bottom';

const VALID_INNER_LAYOUTS: ReadonlyArray<InnerLayout> = [
    'normal',
    'equal',
    'center',
    'bottom',
    'last-bottom',
];

interface GridItemSaveAttributes {
    readonly innerLayout: InnerLayout;
    readonly gridColumnSpan: number;
    readonly gridRowSpan: number;
    readonly artisanpackFlex?: ArtisanpackFlexAttribute | null;
}

function clampSpan(value: number | undefined, max: number, fallback: number): number {
    const next =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : fallback;
    if (next < 1) {
        return 1;
    }
    if (next > max) {
        return max;
    }
    return next;
}

export default function GridItemSave({
    attributes,
}: {
    attributes: GridItemSaveAttributes;
}): ReactElement {
    const gridColumnSpan = clampSpan(attributes.gridColumnSpan, 12, 1);
    const gridRowSpan = clampSpan(attributes.gridRowSpan, 12, 1);
    const innerLayout = (VALID_INNER_LAYOUTS as ReadonlyArray<string>).includes(
        attributes.innerLayout
    )
        ? attributes.innerLayout
        : 'normal';

    const flexResult = serializeFlex(
        attributes.artisanpackFlex ?? null,
        new BreakpointRegistry()
    );

    const className = [
        'ap-grid-item',
        `ap-grid-item-layout-${innerLayout}`,
        `ap-grid-item-span-${gridColumnSpan}-base-columns`,
        `ap-grid-item-span-${gridRowSpan}-base-row`,
        ...flexResult.classes,
    ].join(' ');

    const blockProps = (useBlockProps.save as any)({ className });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
