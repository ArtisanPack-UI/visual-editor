/**
 * Button — deprecation chain.
 *
 * Adapted from `@wordpress/block-library/src/button/deprecated.js`
 * (v9.43.0). Upstream ships a 12-entry chain spanning ~1750 LOC of
 * migrators for borderRadius, font-family, text-align, gradient/color
 * class-name shifts, the legacy `width` (1-4) → `style.dimensions.width`
 * percentage migration, etc. This fork ports the most recent (v11)
 * legacy-width migration and the v1 legacy gradient/color → style
 * migration, which cover the cases V2 documents are most likely to hit.
 * Older deprecations are intentionally not carried — any document
 * shaped to them will need a one-shot CLI migration before it loads.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    getColorClassName,
    useBlockProps,
} from '@wordpress/block-editor';

interface LegacyWidthAttributes {
    readonly width?: number | string;
    readonly style?: { dimensions?: { width?: string } };
    readonly text?: string;
    readonly url?: string;
    readonly title?: string;
    readonly [key: string]: unknown;
}

interface V1Attributes {
    readonly url?: string;
    readonly title?: string;
    readonly text?: string;
    readonly backgroundColor?: string;
    readonly textColor?: string;
    readonly customBackgroundColor?: string;
    readonly customTextColor?: string;
    readonly [key: string]: unknown;
}

const migrateLegacyWidth = (
    attributes: LegacyWidthAttributes
): LegacyWidthAttributes => {
    const { width, ...otherAttributes } = attributes;
    if (!width) {
        return otherAttributes;
    }
    return {
        ...otherAttributes,
        style: {
            ...otherAttributes.style,
            dimensions: {
                ...otherAttributes.style?.dimensions,
                width: `${width}%`,
            },
        },
    };
};

const widthDeprecation = {
    attributes: {
        url: { type: 'string', source: 'attribute', selector: 'a', attribute: 'href' },
        title: { type: 'string', source: 'attribute', selector: 'a,button', attribute: 'title' },
        text: { type: 'string', source: 'html', selector: 'a,button' },
        width: { type: 'number' },
    },
    supports: {
        anchor: true,
        align: false,
        alignWide: false,
        color: { __experimentalSkipSerialization: true, gradients: true },
        reusable: false,
    },
    isEligible: ({ width }: LegacyWidthAttributes): boolean => {
        return (
            typeof width === 'number' &&
            [25, 50, 75, 100].includes(width as number)
        );
    },
    migrate: migrateLegacyWidth,
    save({ attributes }: { attributes: LegacyWidthAttributes }): ReactElement {
        const { text, url, title } = attributes;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = (useBlockProps.save as any)({});
        return (
            <div {...blockProps}>
                <a
                    className="wp-block-button__link"
                    href={url}
                    title={title}
                    dangerouslySetInnerHTML={{ __html: text ?? '' }}
                />
            </div>
        );
    },
};

const v1 = {
    attributes: {
        url: { type: 'string', source: 'attribute', selector: 'a', attribute: 'href' },
        title: { type: 'string', source: 'attribute', selector: 'a,button', attribute: 'title' },
        text: { type: 'string', source: 'html', selector: 'a,button' },
        backgroundColor: { type: 'string' },
        textColor: { type: 'string' },
        customBackgroundColor: { type: 'string' },
        customTextColor: { type: 'string' },
    },
    isEligible: ({
        customBackgroundColor,
        customTextColor,
    }: V1Attributes): boolean => {
        return !!customBackgroundColor || !!customTextColor;
    },
    migrate(attributes: V1Attributes) {
        const {
            customBackgroundColor,
            customTextColor,
            ...rest
        } = attributes;
        return {
            ...rest,
            style: {
                color: {
                    ...(customBackgroundColor && {
                        background: customBackgroundColor,
                    }),
                    ...(customTextColor && { text: customTextColor }),
                },
            },
        };
    },
    save({ attributes }: { attributes: V1Attributes }): ReactElement {
        const {
            url,
            text,
            title,
            backgroundColor,
            textColor,
            customBackgroundColor,
            customTextColor,
        } = attributes;
        const textClass = getColorClassName('color', textColor);
        const backgroundClass = getColorClassName(
            'background-color',
            backgroundColor
        );
        const linkClasses = clsx('wp-block-button__link', {
            'has-text-color': textColor || customTextColor,
            [textClass as string]: textClass,
            'has-background': backgroundColor || customBackgroundColor,
            [backgroundClass as string]: backgroundClass,
        });
        const buttonStyle = {
            backgroundColor: backgroundClass ? undefined : customBackgroundColor,
            color: textClass ? undefined : customTextColor,
        };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = (useBlockProps.save as any)({});
        return (
            <div {...blockProps}>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                {(RichText as any).Content ? (
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    <RichText.Content
                        tagName="a"
                        className={linkClasses}
                        href={url}
                        title={title}
                        style={buttonStyle}
                        value={text}
                    />
                ) : (
                    <a
                        className={linkClasses}
                        href={url}
                        title={title}
                        style={buttonStyle}
                        dangerouslySetInnerHTML={{ __html: text ?? '' }}
                    />
                )}
            </div>
        );
    },
};

export default [widthDeprecation, v1];
