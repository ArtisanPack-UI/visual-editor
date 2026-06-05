/**
 * Buttons — edit component.
 *
 * Ported from `@wordpress/block-library/src/buttons/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-buttons` class to `useBlockProps` and swaps
 * the inner-block default + allowed list to `artisanpack/button` so the
 * fork forces its own child rather than inheriting `core/button` inserts.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';

interface ButtonsAttributes {
    readonly fontSize?: string;
    readonly layout?: { orientation?: 'horizontal' | 'vertical' };
    readonly style?: { typography?: { fontSize?: string } };
}

const DEFAULT_BLOCK = {
    name: 'artisanpack/button',
    attributesToCopy: [
        'backgroundColor',
        'border',
        'className',
        'fontFamily',
        'fontSize',
        'gradient',
        'style',
        'textColor',
        'width',
    ],
};

export default function ButtonsEdit({
    attributes,
    className,
}: {
    attributes: ButtonsAttributes;
    className?: string;
}): ReactElement {
    const { fontSize, layout, style } = attributes;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps as any)({
        className: clsx('wp-block-buttons', className, {
            'has-custom-font-size': fontSize || style?.typography?.fontSize,
        }),
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = (useInnerBlocksProps as any)(blockProps, {
        defaultBlock: DEFAULT_BLOCK,
        directInsert: true,
        template: [['artisanpack/button']],
        templateInsertUpdatesSelection: true,
        orientation: layout?.orientation ?? 'horizontal',
        allowedBlocks: ['artisanpack/button'],
    });

    return <div {...innerBlocksProps} />;
}
