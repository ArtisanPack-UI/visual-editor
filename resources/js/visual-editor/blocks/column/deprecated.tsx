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
    // #925 — v1's save is a bare <div>, so almost any column whose saved
    // HTML lacks its support output matches it, not just legacy numeric
    // widths. Only convert a numeric width; otherwise leave `width` out
    // rather than storing "undefined%". The fold of unexpected root
    // classes into `className` also picks up `wp-block-column`, which the
    // current save writes itself, so drop that token.
    migrate(attributes: V1Attributes & Record<string, unknown>) {
        const { width, className, ...rest } = attributes;
        const migrated: Record<string, unknown> = { ...rest };

        if (typeof width === 'number' && isFinite(width)) {
            migrated.width = `${width}%`;
        }

        const classes =
            typeof className === 'string'
                ? className.split(/\s+/).filter((token) => token !== '' && token !== 'wp-block-column')
                : [];

        if (classes.length > 0) {
            migrated.className = classes.join(' ');
        }

        return migrated;
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
