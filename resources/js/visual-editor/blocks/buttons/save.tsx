/**
 * Buttons — save component.
 *
 * Ported from `@wordpress/block-library/src/buttons/save.js` (v9.43.0).
 * Adds an explicit `wp-block-buttons` class for renderer parity.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';

interface ButtonsSaveAttributes {
    readonly fontSize?: string;
    readonly style?: { typography?: { fontSize?: string } };
}

export default function buttonsSave({
    attributes,
    className,
}: {
    attributes: ButtonsSaveAttributes;
    className?: string;
}): ReactElement {
    const { fontSize, style } = attributes;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className: clsx('wp-block-buttons', className, {
            'has-custom-font-size': fontSize || style?.typography?.fontSize,
        }),
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
