/**
 * Column — save component.
 *
 * Ported from `@wordpress/block-library/src/column/save.js` (v9.43.0).
 * Adds an explicit `wp-block-column` class so saved markup is
 * byte-equivalent regardless of namespace.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

import {
    serializeFlex,
    type ArtisanpackFlexAttribute,
} from '../_shared/flex-controls';
import { BreakpointRegistry } from '../../responsive/registry';

interface ColumnSaveAttributes {
    readonly verticalAlignment?: string;
    readonly width?: string | number;
    readonly artisanpackFlex?: ArtisanpackFlexAttribute | null;
}

export default function columnSave({
    attributes,
}: {
    attributes: ColumnSaveAttributes;
}): ReactElement {
    const { verticalAlignment, width } = attributes;
    const flexResult = serializeFlex(
        attributes.artisanpackFlex ?? null,
        new BreakpointRegistry(),
    );
    const wrapperClasses = clsx(
        'wp-block-column',
        {
            [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        },
        flexResult.classes,
    );

    let style: { flexBasis: string } | undefined;
    if (width && /\d/.test(String(width))) {
        let flexBasis: string = Number.isFinite(width as number)
            ? `${width}%`
            : (width as string);
        if (
            !Number.isFinite(width as number) &&
            typeof width === 'string' &&
            width.endsWith('%')
        ) {
            const multiplier = 1000000000000;
            flexBasis =
                Math.round(Number.parseFloat(width) * multiplier) /
                    multiplier +
                '%';
        }
        style = { flexBasis };
    }

    const blockProps = (useBlockProps.save as any)({
        className: wrapperClasses,
        style,
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
