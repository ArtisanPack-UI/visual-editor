/**
 * Group — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/group/deprecated.js`
 * (v9.43.0). All 5 upstream entries preserved; save markup adapted to
 * TypeScript. Class/attribute logic is byte-equivalent to upstream.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    InnerBlocks,
    getColorClassName,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';

interface LegacyAttributes {
    readonly tagName?: string;
    readonly backgroundColor?: string;
    readonly customBackgroundColor?: string;
    readonly textColor?: string;
    readonly customTextColor?: string;
    readonly layout?: {
        inherit?: boolean;
        contentSize?: string;
        type?: string;
        [key: string]: unknown;
    };
    readonly [key: string]: unknown;
}

const migrateAttributes = (
    attributes: LegacyAttributes
): LegacyAttributes => {
    let next: LegacyAttributes = attributes;
    if (!next.tagName) {
        next = { ...next, tagName: 'div' };
    }

    if (!next.customTextColor && !next.customBackgroundColor) {
        return next;
    }
    const style: { color: { text?: string; background?: string } } = {
        color: {},
    };
    if (next.customTextColor) {
        style.color.text = next.customTextColor;
    }
    if (next.customBackgroundColor) {
        style.color.background = next.customBackgroundColor;
    }

    const { customTextColor, customBackgroundColor, ...restAttributes } = next;
    void customTextColor;
    void customBackgroundColor;

    return {
        ...restAttributes,
        style,
    };
};

const deprecated = [
    // Version with default layout.
    {
        attributes: {
            tagName: { type: 'string', default: 'div' },
            templateLock: {
                type: ['string', 'boolean'],
                enum: ['all', 'insert', false],
            },
        },
        supports: {
            __experimentalOnEnter: true,
            __experimentalSettings: true,
            align: ['wide', 'full'],
            anchor: true,
            ariaLabel: true,
            html: false,
        },
        save({
            attributes,
        }: {
            attributes: { tagName?: string };
        }): ReactElement {
            const Tag = (attributes.tagName ?? 'div') as keyof JSX.IntrinsicElements;
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const inner = (useInnerBlocksProps.save as any)(
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                (useBlockProps.save as any)()
            );
            return <Tag {...inner} />;
        },
        isEligible: ({
            layout,
        }: {
            layout?: { inherit?: boolean; contentSize?: string; type?: string };
        }): boolean =>
            !!(
                layout?.inherit ||
                (layout?.contentSize && layout?.type !== 'constrained')
            ),
        migrate: (attributes: LegacyAttributes): LegacyAttributes | undefined => {
            const { layout = null } = attributes;
            if (layout?.inherit || layout?.contentSize) {
                return {
                    ...attributes,
                    layout: { ...layout, type: 'constrained' },
                };
            }
            return undefined;
        },
    },
    // Version of the block with the double div.
    {
        attributes: {
            tagName: { type: 'string', default: 'div' },
            templateLock: {
                type: ['string', 'boolean'],
                enum: ['all', 'insert', false],
            },
        },
        supports: {
            align: ['wide', 'full'],
            anchor: true,
        },
        save({
            attributes,
        }: {
            attributes: { tagName?: string };
        }): ReactElement {
            const Tag = (attributes.tagName ?? 'div') as keyof JSX.IntrinsicElements;
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const blockProps = (useBlockProps.save as any)();
            return (
                <Tag {...blockProps}>
                    <div className="wp-block-group__inner-container">
                        <InnerBlocks.Content />
                    </div>
                </Tag>
            );
        },
    },
    // Version of the block without global styles support
    {
        attributes: {
            backgroundColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
            textColor: { type: 'string' },
            customTextColor: { type: 'string' },
        },
        supports: {
            align: ['wide', 'full'],
            anchor: true,
            html: false,
        },
        migrate: migrateAttributes,
        save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
            const {
                backgroundColor,
                customBackgroundColor,
                textColor,
                customTextColor,
            } = attributes;

            const backgroundClass = getColorClassName(
                'background-color',
                backgroundColor
            );
            const textClass = getColorClassName('color', textColor);
            const className = clsx(backgroundClass, textClass, {
                'has-text-color': textColor || customTextColor,
                'has-background': backgroundColor || customBackgroundColor,
            });

            const styles = {
                backgroundColor: backgroundClass
                    ? undefined
                    : customBackgroundColor,
                color: textClass ? undefined : customTextColor,
            };

            return (
                <div className={className} style={styles}>
                    <div className="wp-block-group__inner-container">
                        <InnerBlocks.Content />
                    </div>
                </div>
            );
        },
    },
    // Version of the group block with a bug that made text color class not applied.
    {
        attributes: {
            backgroundColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
            textColor: { type: 'string' },
            customTextColor: { type: 'string' },
        },
        migrate: migrateAttributes,
        supports: {
            align: ['wide', 'full'],
            anchor: true,
            html: false,
        },
        save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
            const {
                backgroundColor,
                customBackgroundColor,
                textColor,
                customTextColor,
            } = attributes;

            const backgroundClass = getColorClassName(
                'background-color',
                backgroundColor
            );
            const className = clsx(backgroundClass, {
                'has-text-color': textColor || customTextColor,
                'has-background': backgroundColor || customBackgroundColor,
            });

            const styles = {
                backgroundColor: backgroundClass
                    ? undefined
                    : customBackgroundColor,
                color: customTextColor,
            };

            return (
                <div className={className} style={styles}>
                    <div className="wp-block-group__inner-container">
                        <InnerBlocks.Content />
                    </div>
                </div>
            );
        },
    },
    // v1 of group block. Deprecated to add an inner-container div around `InnerBlocks.Content`.
    {
        attributes: {
            backgroundColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
        },
        supports: {
            align: ['wide', 'full'],
            anchor: true,
            html: false,
        },
        migrate: migrateAttributes,
        save({ attributes }: { attributes: LegacyAttributes }): ReactElement {
            const { backgroundColor, customBackgroundColor } = attributes;

            const backgroundClass = getColorClassName(
                'background-color',
                backgroundColor
            );
            const className = clsx(backgroundClass, {
                'has-background': backgroundColor || customBackgroundColor,
            });

            const styles = {
                backgroundColor: backgroundClass
                    ? undefined
                    : customBackgroundColor,
            };

            return (
                <div className={className} style={styles}>
                    <InnerBlocks.Content />
                </div>
            );
        },
    },
];

export default deprecated;
