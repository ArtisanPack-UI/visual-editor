/**
 * Button — save component.
 *
 * Ported from `@wordpress/block-library/src/button/save.js` (v9.43.0).
 * Adds an explicit `wp-block-button` class to `useBlockProps.save` so
 * the saved markup is byte-equivalent regardless of namespace.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetColorClassesAndStyles as getColorClassesAndStyles,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetSpacingClassesAndStyles as getSpacingClassesAndStyles,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetShadowClassesAndStyles as getShadowClassesAndStyles,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetElementClassName,
    getTypographyClassesAndStyles,
} from '@wordpress/block-editor';

interface ButtonSaveAttributes {
    readonly tagName?: 'a' | 'button';
    readonly type?: string;
    readonly fontSize?: string;
    readonly linkTarget?: string;
    readonly rel?: string;
    readonly style?: {
        border?: { radius?: number };
        typography?: { fontSize?: string };
    };
    readonly text?: string;
    readonly title?: string;
    readonly url?: string;
}

export default function buttonSave({
    attributes,
}: {
    attributes: ButtonSaveAttributes;
}): ReactElement {
    const {
        tagName,
        type,
        fontSize,
        linkTarget,
        rel,
        style,
        text,
        title,
        url,
    } = attributes;
    const TagName = (tagName || 'a') as 'a' | 'button';
    const isButtonTag = 'button' === TagName;
    const buttonType = type || 'button';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const borderProps = (getBorderClassesAndStyles as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const colorProps = (getColorClassesAndStyles as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const spacingProps = (getSpacingClassesAndStyles as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const shadowProps = (getShadowClassesAndStyles as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const typographyProps = (getTypographyClassesAndStyles as any)(attributes);
    const buttonClasses = clsx(
        'wp-block-button__link',
        colorProps?.className,
        borderProps?.className,
        typographyProps?.className,
        {
            'no-border-radius': style?.border?.radius === 0,
            'has-custom-font-size': fontSize || style?.typography?.fontSize,
        },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (__experimentalGetElementClassName as any)('button')
    );
    const buttonStyle = {
        ...borderProps?.style,
        ...colorProps?.style,
        ...spacingProps?.style,
        ...shadowProps?.style,
        ...typographyProps?.style,
        writingMode: undefined,
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className: 'wp-block-button',
    });

    return (
        <div {...blockProps}>
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {(RichText as any).Content ? (
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                <RichText.Content
                    tagName={TagName}
                    type={isButtonTag ? buttonType : undefined}
                    className={buttonClasses}
                    href={isButtonTag ? undefined : url}
                    title={title}
                    style={buttonStyle}
                    value={text}
                    target={isButtonTag ? undefined : linkTarget}
                    rel={isButtonTag ? undefined : rel}
                />
            ) : (
                <TagName
                    className={buttonClasses}
                    style={buttonStyle}
                    {...(isButtonTag
                        ? { type: buttonType }
                        : { href: url, target: linkTarget, rel })}
                    title={title}
                    dangerouslySetInnerHTML={{ __html: text ?? '' }}
                />
            )}
        </div>
    );
}
