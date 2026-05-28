/**
 * Button — edit component.
 *
 * Ported from `@wordpress/block-library/src/button/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-button` class to `useBlockProps`. The
 * upstream edit pulls in private block-editor APIs, `LinkControl`,
 * `Popover`-anchored link UI, a dimension-preset resolver, and an
 * `onEnter` rich-text splitter — all of which are simplified to a
 * straightforward RichText + URL/rel text controls here so the fork
 * doesn't depend on `unlock( blockEditorPrivateApis )`.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    BlockControls,
    InspectorControls,
    RichText,
    useBlockProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalLinkControl as LinkControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseBorderProps as useBorderProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseColorProps as useColorProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetSpacingClassesAndStyles as useSpacingProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetShadowClassesAndStyles as useShadowProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalGetElementClassName,
    getTypographyClassesAndStyles as useTypographyProps,
} from '@wordpress/block-editor';
import {
    Popover,
    SelectControl,
    TextControl,
    ToolbarButton,
} from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon, linkOff as linkOffIcon } from '@wordpress/icons';

import { getUpdatedLinkAttributes } from './get-updated-link-attributes';

import { getWidthClasses } from './utils';

interface ButtonAttributes {
    readonly tagName?: 'a' | 'button';
    readonly placeholder?: string;
    readonly rel?: string;
    readonly style?: {
        border?: { radius?: number };
        dimensions?: { width?: string };
        typography?: { fontSize?: string };
    };
    readonly text?: string;
    readonly url?: string;
    readonly linkTarget?: '_self' | '_blank' | string;
}

export default function ButtonEdit({
    attributes,
    setAttributes,
    className,
}: {
    attributes: ButtonAttributes;
    setAttributes: (attrs: Partial<ButtonAttributes>) => void;
    className?: string;
}): ReactElement {
    const { tagName, placeholder, rel, style, text, url } = attributes;
    const TagName = (tagName || 'a') as 'a' | 'button';
    const isLinkTag = 'a' === TagName;
    const width = style?.dimensions?.width;
    const [isLinkPickerOpen, setIsLinkPickerOpen] = useState(false);
    const linkAnchorRef = useRef<HTMLDivElement | null>(null);
    const openInNewTab = attributes.linkTarget === '_blank';

    const unlinkButton = (): void => {
        setAttributes({
            url: undefined,
            rel: undefined,
            linkTarget: undefined,
        });
        setIsLinkPickerOpen(false);
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const borderProps = (useBorderProps as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const colorProps = (useColorProps as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const spacingProps = (useSpacingProps as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const shadowProps = (useShadowProps as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const typographyProps = (useTypographyProps as any)(attributes);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps as any)({
        className: 'wp-block-button',
    });

    const classes = clsx(blockProps.className, getWidthClasses(width));
    const linkClassName = clsx(
        className,
        'wp-block-button__link',
        colorProps?.className,
        borderProps?.className,
        typographyProps?.className,
        {
            'no-border-radius': style?.border?.radius === 0,
            'has-custom-font-size': !!style?.typography?.fontSize,
        },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (__experimentalGetElementClassName as any)('button')
    );

    return (
        <>
            <div {...blockProps} className={classes}>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                <RichText
                    aria-label={__('Button text')}
                    placeholder={placeholder || __('Add text…')}
                    value={text}
                    onChange={(value: string) =>
                        setAttributes({ text: value })
                    }
                    withoutInteractiveFormatting
                    className={linkClassName}
                    style={{
                        ...borderProps?.style,
                        ...colorProps?.style,
                        ...spacingProps?.style,
                        ...shadowProps?.style,
                        ...typographyProps?.style,
                        writingMode: undefined,
                    }}
                    identifier="text"
                />
            </div>
            {isLinkTag && (
                <>
                    <BlockControls group="block">
                        <ToolbarButton
                            // @ts-expect-error - upstream prop
                            name="link"
                            icon={linkIcon}
                            title={__('Link')}
                            shortcut="mod+k"
                            onClick={() => setIsLinkPickerOpen(true)}
                            isActive={!!url}
                        />
                        {url && (
                            <ToolbarButton
                                // @ts-expect-error - upstream prop
                                name="unlink"
                                icon={linkOffIcon}
                                title={__('Unlink')}
                                shortcut="mod+shift+k"
                                onClick={unlinkButton}
                            />
                        )}
                    </BlockControls>
                    <span
                        ref={linkAnchorRef}
                        style={{ position: 'absolute' }}
                        aria-hidden="true"
                    />
                    {isLinkPickerOpen && (
                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                        <Popover
                            // eslint-disable-next-line @typescript-eslint/no-explicit-any
                            placement="bottom"
                            onClose={() => setIsLinkPickerOpen(false)}
                            anchor={linkAnchorRef.current}
                            focusOnMount={'firstElement' as unknown as boolean}
                            shift
                        >
                            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                            <LinkControl
                                value={{
                                    url: url ?? '',
                                    opensInNewTab: openInNewTab,
                                }}
                                onChange={(next: {
                                    url?: string;
                                    opensInNewTab?: boolean;
                                }) => {
                                    const updated = getUpdatedLinkAttributes({
                                        rel,
                                        url: next.url ?? '',
                                        opensInNewTab: next.opensInNewTab,
                                    });
                                    setAttributes({
                                        url: updated.url || undefined,
                                        linkTarget: updated.linkTarget,
                                        rel: updated.rel,
                                    } as Partial<ButtonAttributes>);
                                }}
                                onRemove={unlinkButton}
                                forceIsEditingLink={!url}
                            />
                        </Popover>
                    )}
                </>
            )}
            <InspectorControls group="advanced">
                <SelectControl
                    label={__('HTML element')}
                    value={tagName ?? 'a'}
                    options={[
                        { label: __('Default (<a>)'), value: 'a' },
                        { label: '<button>', value: 'button' },
                    ]}
                    onChange={(value: string) =>
                        setAttributes({ tagName: value as 'a' | 'button' })
                    }
                    // @ts-expect-error - upstream prop
                    __next40pxDefaultSize
                />
                {isLinkTag && (
                    <TextControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        label={__('Link relation')}
                        value={rel ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ rel: value })
                        }
                    />
                )}
            </InspectorControls>
        </>
    );
}
