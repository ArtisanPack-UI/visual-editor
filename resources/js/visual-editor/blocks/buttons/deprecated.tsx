/**
 * Buttons — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/buttons/deprecated.js`
 * (v9.43.0). Both v1 (contentJustification/orientation → layout) and v2
 * (legacy align center/left/right) entries preserved under the new
 * namespace.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

interface MigratableAttributes {
    readonly contentJustification?: string;
    readonly orientation?: string;
    readonly layout?: unknown;
    readonly align?: string;
    readonly [key: string]: unknown;
}

const migrateWithLayout = (
    attributes: MigratableAttributes
): MigratableAttributes => {
    if (!!attributes.layout) {
        return attributes;
    }
    const { contentJustification, orientation, ...updatedAttributes } =
        attributes;
    if (contentJustification || orientation) {
        Object.assign(updatedAttributes, {
            layout: {
                type: 'flex',
                ...(contentJustification && {
                    justifyContent: contentJustification,
                }),
                ...(orientation && { orientation }),
            },
        });
    }
    return updatedAttributes;
};

const v1 = {
    attributes: {
        contentJustification: { type: 'string' },
        orientation: { type: 'string', default: 'horizontal' },
    },
    supports: {
        anchor: true,
        align: ['wide', 'full'],
        __experimentalExposeControlsToChildren: true,
        spacing: {
            blockGap: true,
            margin: ['top', 'bottom'],
            __experimentalDefaultControls: { blockGap: true },
        },
    },
    isEligible: ({
        contentJustification,
        orientation,
    }: MigratableAttributes): boolean =>
        !!contentJustification || !!orientation,
    migrate: migrateWithLayout,
    save({
        attributes: { contentJustification, orientation },
    }: {
        attributes: MigratableAttributes;
    }): ReactElement {
        return (
            <div
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                {...(useBlockProps.save as any)({
                    className: clsx({
                        [`is-content-justification-${contentJustification}`]:
                            contentJustification,
                        'is-vertical': orientation === 'vertical',
                    }),
                })}
            >
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                {(InnerBlocks as any).Content
                    ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
                      ((InnerBlocks as any).Content as unknown as () => ReactElement)()
                    : null}
            </div>
        );
    },
};

const v2 = {
    supports: {
        align: ['center', 'left', 'right'],
        anchor: true,
    },
    save(): ReactElement {
        return (
            <div>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                {(InnerBlocks as any).Content
                    ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
                      ((InnerBlocks as any).Content as unknown as () => ReactElement)()
                    : null}
            </div>
        );
    },
    isEligible({ align }: MigratableAttributes): boolean {
        return !!align && ['center', 'left', 'right'].includes(align);
    },
    migrate(attributes: MigratableAttributes): MigratableAttributes {
        return migrateWithLayout({
            ...attributes,
            align: undefined,
            contentJustification: attributes.align,
        });
    },
};

export default [v1, v2];
