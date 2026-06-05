/**
 * Paragraph — deprecation chain.
 *
 * Full port of `@wordpress/block-library/src/paragraph/deprecated.js` (v9.43.0).
 * Every historical save shape is preserved so saved markup from `core/paragraph`
 * in old posts deserializes cleanly when the post is opened in the editor
 * registered with `artisanpack/paragraph`. Each entry mirrors the upstream
 * `attributes` / `supports` / `migrate` / `save` exactly; changes here will
 * break CI's deprecation-chain test fixtures.
 */

import clsx from 'clsx';
import { RawHTML } from '@wordpress/element';
import {
    getColorClassName,
    getFontSizeClass,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import { isRTL } from '@wordpress/i18n';

const supports = {
    className: false,
};

const blockAttributes = {
    align: {
        type: 'string',
    },
    content: {
        type: 'string',
        source: 'html',
        selector: 'p',
        default: '',
    },
    dropCap: {
        type: 'boolean',
        default: false,
    },
    placeholder: {
        type: 'string',
    },
    textColor: {
        type: 'string',
    },
    backgroundColor: {
        type: 'string',
    },
    fontSize: {
        type: 'string',
    },
    direction: {
        type: 'string',
        enum: ['ltr', 'rtl'],
    },
    style: {
        type: 'object',
    },
} as const;

interface LegacyAttributes {
    align?: string;
    content?: string;
    dropCap?: boolean;
    direction?: 'ltr' | 'rtl';
    placeholder?: string;
    textColor?: string;
    backgroundColor?: string;
    fontSize?: string | number;
    customTextColor?: string;
    customBackgroundColor?: string;
    customFontSize?: string | number;
    width?: string;
    className?: string;
    style?: Record<string, unknown> & {
        typography?: Record<string, unknown> & { textAlign?: string };
        color?: Record<string, unknown>;
    };
    [key: string]: unknown;
}

const migrateCustomColorsAndFontSizes = (attributes: LegacyAttributes): LegacyAttributes => {
    if (
        !attributes.customTextColor &&
        !attributes.customBackgroundColor &&
        !attributes.customFontSize
    ) {
        return attributes;
    }
    const style: { color?: Record<string, unknown>; typography?: { fontSize?: string | number } } = {};
    if (attributes.customTextColor || attributes.customBackgroundColor) {
        style.color = {};
    }
    if (attributes.customTextColor) {
        (style.color as Record<string, unknown>).text = attributes.customTextColor;
    }
    if (attributes.customBackgroundColor) {
        (style.color as Record<string, unknown>).background = attributes.customBackgroundColor;
    }
    if (attributes.customFontSize) {
        style.typography = { fontSize: attributes.customFontSize };
    }

    const {
        customTextColor,
        customBackgroundColor,
        customFontSize,
        ...restAttributes
    } = attributes;

    return {
        ...restAttributes,
        style,
    };
};

const migrateTextAlign = (attributes: LegacyAttributes): LegacyAttributes => {
    const { align, ...restAttributes } = attributes;
    if (!align) {
        return attributes;
    }
    return {
        ...restAttributes,
        style: {
            ...attributes.style,
            typography: {
                ...attributes.style?.typography,
                textAlign: align,
            },
        },
    };
};

const { style: _omitStyle, ...restBlockAttributes } = blockAttributes;

const deprecated = [
    // v6 — align attribute migrated to style.typography.textAlign.
    {
        supports: {
            className: false,
            typography: {
                fontSize: true,
            },
        },
        attributes: blockAttributes,
        isEligible(attributes: LegacyAttributes) {
            return (
                !!attributes.align ||
                !!attributes.className?.match(
                    /\bhas-text-align-(left|center|right)\b/
                )
            );
        },
        save({ attributes }: { attributes: LegacyAttributes }) {
            const { align, content, dropCap, direction } = attributes;
            const className = clsx({
                'has-drop-cap':
                    align === (isRTL() ? 'left' : 'right') ||
                    align === 'center'
                        ? false
                        : dropCap,
                [`has-text-align-${align}`]: align,
            });

            return (
                <p {...useBlockProps.save({ className, dir: direction })}>
                    <RichText.Content value={content} />
                </p>
            );
        },
        migrate: migrateTextAlign,
    },
    // v5 — drop-cap-on-aligned-text restriction not enforced.
    {
        supports,
        attributes: {
            ...restBlockAttributes,
            customTextColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
            customFontSize: { type: 'number' },
        },
        migrate: migrateTextAlign,
        save({ attributes }: { attributes: LegacyAttributes }) {
            const { align, content, dropCap, direction } = attributes;
            const className = clsx({
                'has-drop-cap':
                    align === (isRTL() ? 'left' : 'right') ||
                    align === 'center'
                        ? false
                        : dropCap,
                [`has-text-align-${align}`]: align,
            });

            return (
                <p {...useBlockProps.save({ className, dir: direction })}>
                    <RichText.Content value={content} />
                </p>
            );
        },
    },
    // v4 — class-based color + font-size attrs (pre-style-object).
    {
        supports,
        attributes: {
            ...restBlockAttributes,
            customTextColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
            customFontSize: { type: 'number' },
        },
        migrate(attributes: LegacyAttributes) {
            return migrateCustomColorsAndFontSizes(migrateTextAlign(attributes));
        },
        save({ attributes }: { attributes: LegacyAttributes }) {
            const {
                align,
                content,
                dropCap,
                backgroundColor,
                textColor,
                customBackgroundColor,
                customTextColor,
                fontSize,
                customFontSize,
                direction,
            } = attributes;

            const textClass = getColorClassName('color', textColor);
            const backgroundClass = getColorClassName('background-color', backgroundColor);
            const fontSizeClass = getFontSizeClass(fontSize as string | undefined);

            const className = clsx({
                'has-text-color': textColor || customTextColor,
                'has-background': backgroundColor || customBackgroundColor,
                'has-drop-cap': dropCap,
                [`has-text-align-${align}`]: align,
                [fontSizeClass as string]: fontSizeClass,
                [textClass as string]: textClass,
                [backgroundClass as string]: backgroundClass,
            });

            const styles = {
                backgroundColor: backgroundClass ? undefined : customBackgroundColor,
                color: textClass ? undefined : customTextColor,
                fontSize: fontSizeClass ? undefined : customFontSize,
            };

            return (
                <RichText.Content
                    tagName="p"
                    style={styles}
                    className={className ? className : undefined}
                    value={content}
                    dir={direction}
                />
            );
        },
    },
    // v3 — textAlign on style.textAlign instead of class.
    {
        supports,
        attributes: {
            ...restBlockAttributes,
            customTextColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
            customFontSize: { type: 'number' },
        },
        migrate(attributes: LegacyAttributes) {
            return migrateCustomColorsAndFontSizes(migrateTextAlign(attributes));
        },
        save({ attributes }: { attributes: LegacyAttributes }) {
            const {
                align,
                content,
                dropCap,
                backgroundColor,
                textColor,
                customBackgroundColor,
                customTextColor,
                fontSize,
                customFontSize,
                direction,
            } = attributes;

            const textClass = getColorClassName('color', textColor);
            const backgroundClass = getColorClassName('background-color', backgroundColor);
            const fontSizeClass = getFontSizeClass(fontSize as string | undefined);

            const className = clsx({
                'has-text-color': textColor || customTextColor,
                'has-background': backgroundColor || customBackgroundColor,
                'has-drop-cap': dropCap,
                [fontSizeClass as string]: fontSizeClass,
                [textClass as string]: textClass,
                [backgroundClass as string]: backgroundClass,
            });

            const styles = {
                backgroundColor: backgroundClass ? undefined : customBackgroundColor,
                color: textClass ? undefined : customTextColor,
                fontSize: fontSizeClass ? undefined : customFontSize,
                textAlign: align,
            };

            return (
                <RichText.Content
                    tagName="p"
                    style={styles}
                    className={className ? className : undefined}
                    value={content}
                    dir={direction}
                />
            );
        },
    },
    // v2 — width-based alignment + numeric font size.
    {
        supports,
        attributes: {
            ...restBlockAttributes,
            customTextColor: { type: 'string' },
            customBackgroundColor: { type: 'string' },
            customFontSize: { type: 'number' },
            width: { type: 'string' },
        },
        migrate(attributes: LegacyAttributes) {
            return migrateCustomColorsAndFontSizes(migrateTextAlign(attributes));
        },
        save({ attributes }: { attributes: LegacyAttributes }) {
            const {
                width,
                align,
                content,
                dropCap,
                backgroundColor,
                textColor,
                customBackgroundColor,
                customTextColor,
                fontSize,
                customFontSize,
            } = attributes;

            const textClass = getColorClassName('color', textColor);
            const backgroundClass = getColorClassName('background-color', backgroundColor);
            const fontSizeClass = fontSize && `is-${fontSize}-text`;

            const className = clsx({
                [`align${width}`]: width,
                'has-background': backgroundColor || customBackgroundColor,
                'has-drop-cap': dropCap,
                [fontSizeClass as string]: fontSizeClass,
                [textClass as string]: textClass,
                [backgroundClass as string]: backgroundClass,
            });

            const styles = {
                backgroundColor: backgroundClass ? undefined : customBackgroundColor,
                color: textClass ? undefined : customTextColor,
                fontSize: fontSizeClass ? undefined : customFontSize,
                textAlign: align,
            };

            return (
                <RichText.Content
                    tagName="p"
                    style={styles}
                    className={className ? className : undefined}
                    value={content}
                />
            );
        },
    },
    // v1 — pre-classname era.
    {
        supports,
        attributes: {
            ...restBlockAttributes,
            fontSize: { type: 'number' },
        },
        save({ attributes }: { attributes: LegacyAttributes }) {
            const {
                width,
                align,
                content,
                dropCap,
                backgroundColor,
                textColor,
                fontSize,
            } = attributes;
            const className = clsx({
                [`align${width}`]: width,
                'has-background': backgroundColor,
                'has-drop-cap': dropCap,
            });
            const styles = {
                backgroundColor,
                color: textColor,
                fontSize,
                textAlign: align,
            };

            return (
                <p style={styles} className={className ? className : undefined}>
                    {content}
                </p>
            );
        },
        migrate(attributes: LegacyAttributes) {
            return migrateCustomColorsAndFontSizes(
                migrateTextAlign({
                    ...attributes,
                    customFontSize: Number.isFinite(attributes.fontSize)
                        ? (attributes.fontSize as number)
                        : undefined,
                    customTextColor:
                        attributes.textColor && '#' === attributes.textColor[0]
                            ? attributes.textColor
                            : undefined,
                    customBackgroundColor:
                        attributes.backgroundColor &&
                        '#' === attributes.backgroundColor[0]
                            ? attributes.backgroundColor
                            : undefined,
                })
            );
        },
    },
    // v0 — raw HTML content (pre-rich-text).
    {
        supports,
        attributes: {
            ...blockAttributes,
            content: {
                type: 'string',
                source: 'html',
                default: '',
            },
        },
        save({ attributes }: { attributes: LegacyAttributes }) {
            return <RawHTML>{attributes.content}</RawHTML>;
        },
        migrate: (attributes: LegacyAttributes) => attributes,
    },
];

export default deprecated;
