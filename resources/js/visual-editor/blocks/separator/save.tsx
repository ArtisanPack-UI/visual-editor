/**
 * Separator — save component.
 *
 * Ported from `@wordpress/block-library/src/separator/save.js` (v9.43.0).
 * Adds an explicit `wp-block-separator` class so the saved markup is
 * byte-equivalent regardless of namespace.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    getColorClassName,
    useBlockProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetColorClassesAndStyles as getColorClassesAndStyles,
} from '@wordpress/block-editor';

interface SeparatorSaveAttributes {
    readonly backgroundColor?: string;
    readonly style?: { color?: { background?: string } };
    readonly opacity?: string;
    readonly tagName?: 'hr' | 'div';
}

export default function separatorSave({
    attributes,
}: {
    attributes: SeparatorSaveAttributes;
}): ReactElement {
    const { backgroundColor, style, opacity, tagName } = attributes;
    const Tag = (tagName ?? 'hr') as 'hr' | 'div';
    const customColor = style?.color?.background;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const colorProps = (getColorClassesAndStyles as any)(attributes);
    const colorClass = getColorClassName('color', backgroundColor);

    const className = clsx(
        'wp-block-separator',
        {
            'has-text-color': backgroundColor || customColor,
            [colorClass as string]: colorClass,
            'has-css-opacity': opacity === 'css',
            'has-alpha-channel-opacity': opacity === 'alpha-channel',
        },
        colorProps.className
    );

    const styles = {
        backgroundColor: colorProps?.style?.backgroundColor,
        color: colorClass ? undefined : customColor,
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className,
        style: styles,
    });
    return <Tag {...blockProps} />;
}
