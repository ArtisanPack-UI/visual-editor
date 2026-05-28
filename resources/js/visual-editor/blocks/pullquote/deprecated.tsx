/**
 * Pullquote — deprecation chain.
 *
 * Full port of `@wordpress/block-library/src/pullquote/deprecated.js`
 * (v9.43.0). v5 → v0 cover every historical save shape.
 *
 * Divergence: upstream `v2.save` calls
 * `select(blockEditorStore).getSettings().colors` to resolve a named
 * `mainColor` into a border-color. Calling a `select` inside `save` is
 * an anti-pattern (save must be pure) — and reaches into block-editor
 * private state in a way the fork can't safely depend on. v2.save in
 * this fork omits the named-color border lookup; the migrate function
 * still handles `mainColor` correctly. Legacy pullquote blocks that
 * persisted a default-style + `mainColor` (no `customMainColor`) will
 * fail v2's parity check and fall through to v1 or get re-saved as
 * the current shape.
 */

import clsx from 'clsx';
import {
    getColorClassName,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';

import { SOLID_COLOR_CLASS } from './shared';

interface LegacyPullquoteAttributes {
    value?: string;
    citation?: string;
    mainColor?: string;
    customMainColor?: string;
    textColor?: string;
    customTextColor?: string;
    textAlign?: string;
    align?: string;
    className?: string;
    figureStyle?: string;
    style?: Record<string, unknown>;
    [key: string]: unknown;
}

const blockAttributes = {
    value: {
        type: 'string',
        source: 'html',
        selector: 'blockquote',
        multiline: 'p',
    },
    citation: {
        type: 'string',
        source: 'html',
        selector: 'cite',
        default: '',
    },
    mainColor: { type: 'string' },
    customMainColor: { type: 'string' },
    textColor: { type: 'string' },
    customTextColor: { type: 'string' },
} as const;

function parseBorderColor(styleString: string | undefined): string | undefined {
    if (!styleString) {
        return undefined;
    }
    const matches = styleString.match(/border-color:([^;]+)[;]?/);
    if (matches && matches[1]) {
        return matches[1];
    }
    return undefined;
}

function multilineToInline(value: string | undefined): string {
    const safeValue = value || '<p></p>';
    const padded = `</p>${safeValue}<p>`;
    const values = padded.split('</p><p>');
    values.shift();
    values.pop();
    return values.join('<br>');
}

const v5 = {
    attributes: {
        value: {
            type: 'string',
            source: 'html',
            selector: 'blockquote',
            multiline: 'p',
            role: 'content',
        },
        citation: {
            type: 'string',
            source: 'html',
            selector: 'cite',
            default: '',
            role: 'content',
        },
        textAlign: { type: 'string' },
    },
    supports: {
        anchor: true,
        align: ['left', 'right', 'wide', 'full'],
        color: {
            gradients: true,
            background: true,
            link: true,
        },
        typography: { fontSize: true, lineHeight: true },
        __experimentalBorder: { color: true, radius: true, style: true, width: true },
    },
    save({ attributes }: { attributes: LegacyPullquoteAttributes }) {
        const { textAlign, citation, value } = attributes;
        const shouldShowCitation = !RichText.isEmpty(citation);
        return (
            <figure
                {...useBlockProps.save({
                    className: clsx({
                        [`has-text-align-${textAlign}`]: textAlign,
                    }),
                })}
            >
                <blockquote>
                    <RichText.Content value={value} multiline />
                    {shouldShowCitation && (
                        <RichText.Content tagName="cite" value={citation} />
                    )}
                </blockquote>
            </figure>
        );
    },
    migrate({ value, ...attributes }: LegacyPullquoteAttributes) {
        return {
            value: multilineToInline(value),
            ...attributes,
        };
    },
};

const v4 = {
    attributes: { ...blockAttributes },
    supports: {
        anchor: true,
        align: ['left', 'right', 'wide', 'full'],
        color: { gradients: true, background: true, link: true },
        __experimentalBorder: { color: true, radius: true, style: true, width: true },
    },
    save({ attributes }: { attributes: LegacyPullquoteAttributes }) {
        const {
            mainColor,
            customMainColor,
            customTextColor,
            textColor,
            value,
            citation,
            className,
        } = attributes;
        const isSolidColorStyle = className?.includes(SOLID_COLOR_CLASS);

        let figureClasses: string | undefined;
        let figureStyles: Record<string, unknown> | undefined;

        if (isSolidColorStyle) {
            const backgroundClass = getColorClassName('background-color', mainColor);
            figureClasses = clsx({
                'has-background': backgroundClass || customMainColor,
                [backgroundClass as string]: backgroundClass,
            });
            figureStyles = {
                backgroundColor: backgroundClass ? undefined : customMainColor,
            };
        } else if (customMainColor) {
            figureStyles = { borderColor: customMainColor };
        }

        const blockquoteTextColorClass = getColorClassName('color', textColor);
        const blockquoteClasses = clsx({
            'has-text-color': textColor || customTextColor,
            [blockquoteTextColorClass as string]: blockquoteTextColorClass,
        });
        const blockquoteStyles = blockquoteTextColorClass
            ? undefined
            : { color: customTextColor };

        return (
            <figure
                {...useBlockProps.save({
                    className: figureClasses,
                    style: figureStyles,
                })}
            >
                <blockquote className={blockquoteClasses} style={blockquoteStyles}>
                    <RichText.Content value={value} multiline />
                    {!RichText.isEmpty(citation) && (
                        <RichText.Content tagName="cite" value={citation} />
                    )}
                </blockquote>
            </figure>
        );
    },
    migrate({
        value,
        className,
        mainColor,
        customMainColor,
        customTextColor,
        ...attributes
    }: LegacyPullquoteAttributes) {
        const isSolidColorStyle = className?.includes(SOLID_COLOR_CLASS);
        let style: Record<string, unknown> | undefined;

        if (customMainColor) {
            style = isSolidColorStyle
                ? { color: { background: customMainColor } }
                : { border: { color: customMainColor } };
        }

        if (customTextColor && style) {
            const existingColor = (style.color ?? {}) as Record<string, unknown>;
            style.color = { ...existingColor, text: customTextColor };
        }

        return {
            value: multilineToInline(value),
            className,
            backgroundColor: isSolidColorStyle ? mainColor : undefined,
            borderColor: isSolidColorStyle ? undefined : mainColor,
            textAlign: isSolidColorStyle ? 'left' : undefined,
            ...attributes,
            style,
        };
    },
};

const v3 = {
    attributes: {
        ...blockAttributes,
        figureStyle: {
            source: 'attribute',
            selector: 'figure',
            attribute: 'style',
        },
    },
    save({ attributes }: { attributes: LegacyPullquoteAttributes }) {
        const {
            mainColor,
            customMainColor,
            textColor,
            customTextColor,
            value,
            citation,
            className,
            figureStyle,
        } = attributes;
        const isSolidColorStyle = className?.includes(SOLID_COLOR_CLASS);

        let figureClasses: string | undefined;
        let figureStyles: Record<string, unknown> | undefined;

        if (isSolidColorStyle) {
            const backgroundClass = getColorClassName('background-color', mainColor);
            figureClasses = clsx({
                'has-background': backgroundClass || customMainColor,
                [backgroundClass as string]: backgroundClass,
            });
            figureStyles = {
                backgroundColor: backgroundClass ? undefined : customMainColor,
            };
        } else if (customMainColor) {
            figureStyles = { borderColor: customMainColor };
        } else if (mainColor) {
            const borderColor = parseBorderColor(figureStyle);
            figureStyles = { borderColor };
        }

        const blockquoteTextColorClass = getColorClassName('color', textColor);
        const blockquoteClasses =
            textColor || customTextColor
                ? clsx('has-text-color', {
                      [blockquoteTextColorClass as string]: blockquoteTextColorClass,
                  })
                : undefined;
        const blockquoteStyles = blockquoteTextColorClass
            ? undefined
            : { color: customTextColor };

        return (
            <figure className={figureClasses} style={figureStyles}>
                <blockquote className={blockquoteClasses} style={blockquoteStyles}>
                    <RichText.Content value={value} multiline />
                    {!RichText.isEmpty(citation) && (
                        <RichText.Content tagName="cite" value={citation} />
                    )}
                </blockquote>
            </figure>
        );
    },
    migrate({
        value,
        className,
        figureStyle,
        mainColor,
        customMainColor,
        customTextColor,
        ...attributes
    }: LegacyPullquoteAttributes) {
        const isSolidColorStyle = className?.includes(SOLID_COLOR_CLASS);
        let style: Record<string, unknown> | undefined;

        if (customMainColor) {
            style = isSolidColorStyle
                ? { color: { background: customMainColor } }
                : { border: { color: customMainColor } };
        }

        if (customTextColor && style) {
            const existingColor = (style.color ?? {}) as Record<string, unknown>;
            style.color = { ...existingColor, text: customTextColor };
        }

        if (!isSolidColorStyle && mainColor && figureStyle) {
            const borderColor = parseBorderColor(figureStyle);
            if (borderColor) {
                return {
                    value: multilineToInline(value),
                    ...attributes,
                    className,
                    style: { border: { color: borderColor } },
                };
            }
        }
        return {
            value: multilineToInline(value),
            className,
            backgroundColor: isSolidColorStyle ? mainColor : undefined,
            borderColor: isSolidColorStyle ? undefined : mainColor,
            textAlign: isSolidColorStyle ? 'left' : undefined,
            ...attributes,
            style,
        };
    },
};

const v2 = {
    attributes: blockAttributes,
    save({ attributes }: { attributes: LegacyPullquoteAttributes }) {
        const {
            mainColor,
            customMainColor,
            textColor,
            customTextColor,
            value,
            citation,
            className,
        } = attributes;
        const isSolidColorStyle = className?.includes(SOLID_COLOR_CLASS);

        let figureClass: string | undefined;
        let figureStyles: Record<string, unknown> | undefined;

        if (isSolidColorStyle) {
            figureClass = getColorClassName('background-color', mainColor);
            if (!figureClass) {
                figureStyles = { backgroundColor: customMainColor };
            }
        } else if (customMainColor) {
            figureStyles = { borderColor: customMainColor };
        }
        // Divergence: upstream resolves named `mainColor` to a hex value via
        // `select(blockEditorStore).getSettings().colors` here. Calling a
        // store inside save() is impure; we skip it.

        const blockquoteTextColorClass = getColorClassName('color', textColor);
        const blockquoteClasses =
            textColor || customTextColor
                ? clsx('has-text-color', {
                      [blockquoteTextColorClass as string]: blockquoteTextColorClass,
                  })
                : undefined;
        const blockquoteStyle = blockquoteTextColorClass
            ? undefined
            : { color: customTextColor };

        return (
            <figure className={figureClass} style={figureStyles}>
                <blockquote className={blockquoteClasses} style={blockquoteStyle}>
                    <RichText.Content value={value} multiline />
                    {!RichText.isEmpty(citation) && (
                        <RichText.Content tagName="cite" value={citation} />
                    )}
                </blockquote>
            </figure>
        );
    },
    migrate({
        value,
        className,
        mainColor,
        customMainColor,
        customTextColor,
        ...attributes
    }: LegacyPullquoteAttributes) {
        const isSolidColorStyle = className?.includes(SOLID_COLOR_CLASS);
        let style: Record<string, unknown> = {};

        if (customMainColor) {
            style = isSolidColorStyle
                ? { color: { background: customMainColor } }
                : { border: { color: customMainColor } };
        }

        if (customTextColor && style) {
            const existingColor = (style.color ?? {}) as Record<string, unknown>;
            style.color = { ...existingColor, text: customTextColor };
        }

        return {
            value: multilineToInline(value),
            className,
            backgroundColor: isSolidColorStyle ? mainColor : undefined,
            borderColor: isSolidColorStyle ? undefined : mainColor,
            textAlign: isSolidColorStyle ? 'left' : undefined,
            ...attributes,
            style,
        };
    },
};

const v1 = {
    attributes: { ...blockAttributes },
    save({ attributes }: { attributes: LegacyPullquoteAttributes }) {
        const { value, citation } = attributes;
        return (
            <blockquote>
                <RichText.Content value={value} multiline />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="cite" value={citation} />
                )}
            </blockquote>
        );
    },
    migrate({ value, ...attributes }: LegacyPullquoteAttributes) {
        return {
            value: multilineToInline(value),
            ...attributes,
        };
    },
};

const v0 = {
    attributes: {
        ...blockAttributes,
        citation: {
            type: 'string',
            source: 'html',
            selector: 'footer',
        },
        align: {
            type: 'string',
            default: 'none',
        },
    },
    save({ attributes }: { attributes: LegacyPullquoteAttributes }) {
        const { value, citation, align } = attributes;
        return (
            <blockquote className={`align${align}`}>
                <RichText.Content value={value} multiline />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="footer" value={citation} />
                )}
            </blockquote>
        );
    },
    migrate({ value, ...attributes }: LegacyPullquoteAttributes) {
        return {
            value: multilineToInline(value),
            ...attributes,
        };
    },
};

const deprecated = [v5, v4, v3, v2, v1, v0];

export default deprecated;
