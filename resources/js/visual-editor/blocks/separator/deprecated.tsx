/**
 * Separator — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/separator/deprecated.js`
 * (v9.43.0). Single v1 entry preserved verbatim under the new namespace.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { getColorClassName, useBlockProps } from '@wordpress/block-editor';

interface V1Attributes {
    readonly color?: string;
    readonly customColor?: string;
}

const v1 = {
    attributes: {
        color: {
            type: 'string',
        },
        customColor: {
            type: 'string',
        },
    },
    save({ attributes }: { attributes: V1Attributes }): ReactElement {
        const { color, customColor } = attributes;

        const backgroundClass = getColorClassName('background-color', color);
        const colorClass = getColorClassName('color', color);

        const className = clsx({
            'has-text-color has-background': color || customColor,
            [backgroundClass as string]: backgroundClass,
            [colorClass as string]: colorClass,
        });

        const style = {
            backgroundColor: backgroundClass ? undefined : customColor,
            color: colorClass ? undefined : customColor,
        };

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = (useBlockProps.save as any)({ className, style });
        return <hr {...blockProps} />;
    },
    migrate(attributes: V1Attributes & Record<string, unknown>) {
        const { color, customColor, ...restAttributes } = attributes;
        return {
            ...restAttributes,
            backgroundColor: color ? color : undefined,
            opacity: 'css',
            style: customColor
                ? { color: { background: customColor } }
                : undefined,
            tagName: 'hr' as const,
        };
    },
};

export default [v1];
