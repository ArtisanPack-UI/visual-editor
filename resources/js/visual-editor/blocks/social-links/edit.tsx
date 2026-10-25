/**
 * Social Icons — editor-side render (#1023).
 *
 * Ported from `@wordpress/block-library/src/social-links/edit.js`
 * (v9.43.0): icon size, show text and open-in-new-tab settings, icon and
 * icon background colours, and a horizontal flex `<ul>` of
 * `artisanpack/social-link` children with a block appender.
 *
 * Divergences from upstream:
 *
 *   - Every icon's border, corner radius, padding and hover colours (icon,
 *     background, border) are also set here. They ride on the `<ul>` as
 *     `--ap-social-links-*` custom properties applied by
 *     `social-links.css`, and the base icon colours move there too, so
 *     no icon carries an inline style.
 *   - `useToolsPanelDropdownMenuProps` is omitted — it is not reachable
 *     through the block-library `exports` field.
 */

import type { CSSProperties, ReactElement } from 'react';
import {
    ContrastChecker,
    InnerBlocks,
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
    withColors,
    __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
    __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import {
    SelectControl,
    ToggleControl,
    __experimentalBorderControl as BorderControl,
    __experimentalUnitControl as UnitControl,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { ICON_BORDER_STYLES, socialLinksClassName, socialLinksStyle } from './utils';

interface SocialLinksAttributes {
    readonly className?: string;
    readonly iconColor?: string;
    readonly iconColorValue?: string;
    readonly customIconColor?: string;
    readonly iconBackgroundColor?: string;
    readonly iconBackgroundColorValue?: string;
    readonly customIconBackgroundColor?: string;
    readonly openInNewTab?: boolean;
    readonly showLabels?: boolean;
    readonly size?: string;
    readonly iconBorderColorValue?: string;
    readonly iconBorderWidth?: string;
    readonly iconBorderStyle?: string;
    readonly iconBorderRadius?: string;
    readonly iconPadding?: string;
    readonly iconHoverColorValue?: string;
    readonly iconHoverBackgroundColorValue?: string;
    readonly iconHoverBorderColorValue?: string;
    readonly layout?: { readonly orientation?: string };
}

interface BorderValue {
    readonly color?: string;
    readonly style?: string;
    /** `BorderControl` can report a bare number of pixels. */
    readonly width?: string | number;
}

interface ColorObject {
    readonly color?: string;
}

type SetAttributes = (
    next: Partial<SocialLinksAttributes> | ((prev: SocialLinksAttributes) => Partial<SocialLinksAttributes>)
) => void;

export interface SocialLinksEditProps {
    readonly clientId: string;
    readonly attributes: SocialLinksAttributes;
    readonly iconColor: ColorObject;
    readonly iconBackgroundColor: ColorObject;
    readonly isSelected: boolean;
    readonly setAttributes: SetAttributes;
    readonly setIconColor: (color: string | undefined) => void;
    readonly setIconBackgroundColor: (color: string | undefined) => void;
}

interface ColorSetting {
    readonly value: string | undefined;
    readonly onChange: (value: string | undefined) => void;
    readonly label: string;
    readonly resetAllFilter: () => void;
}

export function SocialLinksEdit(props: SocialLinksEditProps): ReactElement {
    const {
        clientId,
        attributes,
        iconBackgroundColor,
        iconColor,
        isSelected,
        setAttributes,
        setIconBackgroundColor,
        setIconColor,
    } = props;
    const {
        iconBackgroundColorValue,
        iconColorValue,
        iconBorderColorValue,
        iconBorderWidth,
        iconBorderStyle,
        iconBorderRadius,
        iconPadding,
        openInNewTab,
        showLabels,
        size,
    } = attributes;

    const { hasSocialIcons, hasSelectedChild } = useSelect(
        (select) => {
            const { getBlockCount, hasSelectedInnerBlock } = select(blockEditorStore) as {
                getBlockCount: (id: string) => number;
                hasSelectedInnerBlock: (id: string) => boolean;
            };
            return {
                hasSocialIcons: getBlockCount(clientId) > 0,
                hasSelectedChild: hasSelectedInnerBlock(clientId),
            };
        },
        [clientId]
    );

    const hasAnySelected = isSelected || hasSelectedChild;
    const resolvedColors = {
        iconColor: iconColor.color,
        iconBackgroundColor: iconBackgroundColor.color,
    };
    const iconStyle = socialLinksStyle(attributes, resolvedColors);
    const blockProps = useBlockProps({
        className: socialLinksClassName(attributes, resolvedColors),
        ...(Object.keys(iconStyle).length > 0 ? { style: iconStyle as CSSProperties } : {}),
    });

    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        templateLock: false,
        orientation: attributes.layout?.orientation ?? 'horizontal',
        __experimentalAppenderTagName: 'li',
        renderAppender: !hasSocialIcons || hasAnySelected ? InnerBlocks.ButtonBlockAppender : undefined,
    });

    const colorSettings: ColorSetting[] = [
        {
            // The custom value is the fallback so a named colour survives a
            // switch to a theme without a matching palette entry.
            value: iconColor.color || iconColorValue,
            onChange: (colorValue) => {
                setIconColor(colorValue);
                setAttributes({ iconColorValue: colorValue });
            },
            label: __('Icon color', TEXT_DOMAIN),
            resetAllFilter: () => {
                setIconColor(undefined);
                setAttributes({ iconColorValue: undefined });
            },
        },
    ];

    const valueSetting = (
        key: 'iconHoverColorValue' | 'iconHoverBackgroundColorValue' | 'iconHoverBorderColorValue',
        label: string
    ): ColorSetting => ({
        value: attributes[key],
        onChange: (colorValue) => setAttributes({ [key]: colorValue }),
        label,
        resetAllFilter: () => setAttributes({ [key]: undefined }),
    });

    // Unlike upstream, Logos Only keeps the background settings: the
    // `has-icon-background-color` rule outranks block-library's
    // `:where(.is-style-logos-only) .wp-social-link { background: none }`.
    colorSettings.push(
        {
            value: iconBackgroundColor.color || iconBackgroundColorValue,
            onChange: (colorValue) => {
                setIconBackgroundColor(colorValue);
                setAttributes({ iconBackgroundColorValue: colorValue });
            },
            label: __('Icon background', TEXT_DOMAIN),
            resetAllFilter: () => {
                setIconBackgroundColor(undefined);
                setAttributes({ iconBackgroundColorValue: undefined });
            },
        },
        valueSetting('iconHoverColorValue', __('Icon hover color', TEXT_DOMAIN)),
        valueSetting('iconHoverBackgroundColorValue', __('Icon hover background', TEXT_DOMAIN)),
        valueSetting('iconHoverBorderColorValue', __('Icon hover border', TEXT_DOMAIN))
    );

    const onIconBorderChange = (next: BorderValue | undefined): void => {
        const style = next?.style;

        setAttributes({
            iconBorderColorValue: next?.color || undefined,
            iconBorderWidth: typeof next?.width === 'number' ? `${next.width}px` : next?.width || undefined,
            iconBorderStyle: style && ICON_BORDER_STYLES.includes(style) ? style : undefined,
        });
    };

    const colorGradientSettings = useMultipleOriginColorsAndGradients();

    return (
        <>
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings', TEXT_DOMAIN)}
                    resetAll={() =>
                        setAttributes({ openInNewTab: false, showLabels: false, size: undefined })
                    }
                >
                    <ToolsPanelItem
                        isShownByDefault
                        hasValue={() => !!size}
                        label={__('Icon size', TEXT_DOMAIN)}
                        onDeselect={() => setAttributes({ size: undefined })}
                    >
                        <SelectControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={__('Icon size', TEXT_DOMAIN)}
                            onChange={(newSize: string) =>
                                setAttributes({ size: newSize === '' ? undefined : newSize })
                            }
                            value={size ?? ''}
                            options={[
                                { label: __('Default', TEXT_DOMAIN), value: '' },
                                { label: __('Small', TEXT_DOMAIN), value: 'has-small-icon-size' },
                                { label: __('Normal', TEXT_DOMAIN), value: 'has-normal-icon-size' },
                                { label: __('Large', TEXT_DOMAIN), value: 'has-large-icon-size' },
                                { label: __('Huge', TEXT_DOMAIN), value: 'has-huge-icon-size' },
                            ] as { label: string; value: string }[]}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Show text', TEXT_DOMAIN)}
                        hasValue={() => !!showLabels}
                        onDeselect={() => setAttributes({ showLabels: false })}
                    >
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label={__('Show text', TEXT_DOMAIN)}
                            checked={!!showLabels}
                            onChange={() => setAttributes({ showLabels: !showLabels })}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Open links in new tab', TEXT_DOMAIN)}
                        hasValue={() => !!openInNewTab}
                        onDeselect={() => setAttributes({ openInNewTab: false })}
                    >
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label={__('Open links in new tab', TEXT_DOMAIN)}
                            checked={!!openInNewTab}
                            onChange={() => setAttributes({ openInNewTab: !openInNewTab })}
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <InspectorControls group="styles">
                <ToolsPanel
                    label={__('Icon style', TEXT_DOMAIN)}
                    resetAll={() => {
                        onIconBorderChange(undefined);
                        setAttributes({ iconBorderRadius: undefined, iconPadding: undefined });
                    }}
                >
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Padding', TEXT_DOMAIN)}
                        hasValue={() => !!iconPadding}
                        onDeselect={() => setAttributes({ iconPadding: undefined })}
                    >
                        <UnitControl
                            __next40pxDefaultSize
                            label={__('Padding', TEXT_DOMAIN)}
                            help={__('Space between each icon and its edge, on every side.', TEXT_DOMAIN)}
                            value={iconPadding ?? ''}
                            units={[
                                { value: 'px', label: 'px' },
                                { value: 'em', label: 'em' },
                                { value: 'rem', label: 'rem' },
                            ]}
                            onChange={(value?: string) => setAttributes({ iconPadding: value || undefined })}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Border', TEXT_DOMAIN)}
                        hasValue={() => !!(iconBorderColorValue || iconBorderWidth || iconBorderStyle)}
                        onDeselect={() => onIconBorderChange(undefined)}
                    >
                        <BorderControl
                            __next40pxDefaultSize
                            label={__('Border', TEXT_DOMAIN)}
                            colors={colorGradientSettings.colors}
                            enableAlpha
                            withSlider
                            value={{
                                color: iconBorderColorValue,
                                style: iconBorderStyle,
                                width: iconBorderWidth,
                            }}
                            onChange={onIconBorderChange}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Radius', TEXT_DOMAIN)}
                        hasValue={() => !!iconBorderRadius}
                        onDeselect={() => setAttributes({ iconBorderRadius: undefined })}
                    >
                        <UnitControl
                            __next40pxDefaultSize
                            label={__('Radius', TEXT_DOMAIN)}
                            value={iconBorderRadius ?? ''}
                            units={[
                                { value: 'px', label: 'px' },
                                { value: '%', label: '%' },
                                { value: 'em', label: 'em' },
                                { value: 'rem', label: 'rem' },
                            ]}
                            onChange={(value?: string) => setAttributes({ iconBorderRadius: value || undefined })}
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            {colorGradientSettings.hasColorsOrGradients && (
                <InspectorControls group="color">
                    {colorSettings.map(({ onChange, label, value, resetAllFilter }) => (
                        <ColorGradientSettingsDropdown
                            key={`social-links-color-${label}`}
                            __experimentalIsRenderedInSidebar
                            settings={[
                                {
                                    colorValue: value,
                                    label,
                                    onColorChange: onChange,
                                    isShownByDefault: true,
                                    resetAllFilter,
                                    enableAlpha: true,
                                    clearable: true,
                                },
                            ]}
                            panelId={clientId}
                            {...colorGradientSettings}
                        />
                    ))}
                    <ContrastChecker
                        textColor={iconColorValue}
                        backgroundColor={iconBackgroundColorValue}
                        isLargeText={false}
                    />
                </InspectorControls>
            )}
            <ul {...innerBlocksProps} />
        </>
    );
}

export default withColors({
    iconColor: 'icon-color',
    iconBackgroundColor: 'icon-background-color',
})(SocialLinksEdit);
