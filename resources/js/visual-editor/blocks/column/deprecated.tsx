/**
 * Column — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/column/deprecated.js`
 * (v9.43.0). Single v1 entry preserved under the artisanpack namespace.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import clsx from 'clsx';
import { InnerBlocks } from '@wordpress/block-editor';

interface V1Attributes {
    readonly verticalAlignment?: string;
    readonly width?: number;
}

const v1 = {
    attributes: {
        verticalAlignment: {
            type: 'string',
        },
        width: {
            type: 'number',
            min: 0,
            max: 100,
        },
    },
    isEligible({ width }: V1Attributes): boolean {
        return typeof width === 'number' && isFinite(width);
    },
    migrate(attributes: V1Attributes & Record<string, unknown>) {
        return {
            ...attributes,
            width: `${attributes.width}%`,
        };
    },
    save({ attributes }: { attributes: V1Attributes }): ReactElement {
        const { verticalAlignment, width } = attributes;
        const wrapperClasses = clsx({
            [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
        });
        const style =
            typeof width === 'number' && isFinite(width)
                ? { flexBasis: `${width}%` }
                : undefined;
        return (
            <div className={wrapperClasses} style={style}>
                <InnerBlocks.Content />
            </div>
        );
    },
};

export default [v1];
