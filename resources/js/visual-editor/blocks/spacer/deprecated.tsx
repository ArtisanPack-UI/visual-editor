/**
 * Spacer — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/spacer/deprecated.js`
 * (v9.43.0). Single v1 entry preserved verbatim under the new namespace.
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

interface V1Attributes {
    readonly height?: number;
    readonly width?: number;
    readonly [key: string]: unknown;
}

const v1 = {
    attributes: {
        height: {
            type: 'number',
            default: 100,
        },
        width: {
            type: 'number',
        },
    },
    migrate(attributes: V1Attributes): Record<string, unknown> {
        const { height, width } = attributes;
        return {
            ...attributes,
            width: width !== undefined ? `${width}px` : undefined,
            height: height !== undefined ? `${height}px` : undefined,
        };
    },
    save({ attributes }: { attributes: V1Attributes }): ReactElement {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = (useBlockProps.save as any)({
            style: {
                height: attributes.height,
                width: attributes.width,
            },
            'aria-hidden': true,
        });
        return <div {...blockProps} />;
    },
};

export default [v1];
