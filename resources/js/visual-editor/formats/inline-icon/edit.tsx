/**
 * Inline-icon RichText format — toolbar button, chooser, and popover (#717).
 *
 * The `edit` component drives three surfaces:
 *   1. A formatting-toolbar button that opens the chooser at the caret.
 *   2. A tabbed chooser Modal reusing the Icon block's picker
 *      ({@link IconPickerPanel}) for registered-set search and
 *      {@link CustomSvgControl} for sanitized custom SVG.
 *   3. An inline popover on an active icon with inherit-first size /
 *      colour overrides plus replace / remove and an optional a11y label.
 */

import { useState } from 'react';
import type { ReactElement, RefObject } from 'react';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import {
    Button,
    ColorPalette,
    Flex,
    FlexItem,
    Modal,
    Popover,
    RangeControl,
    TabPanel,
    TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useAnchor } from '@wordpress/rich-text';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import CustomSvgControl from '../../blocks/icon/custom-svg-control';
import { IconPickerPanel } from '../../blocks/icon/icon-picker';
import IconInserterIcon from '../../blocks/icon/inserter-icon';
import type { IconRef } from '../../blocks/icon/types';

import { fetchIconSvg } from './fetch-svg';
import {
    FORMAT_NAME,
    INLINE_ICON_CLASS,
    buildCustomSvgObject,
    buildInlineIconAttributes,
    buildSetIconObject,
    parseInlineIconStyle,
    type InlineIconObject,
    type InlineIconOptions,
} from './settings';
import {
    insertInlineIcon,
    removeActiveInlineIcon,
    replaceActiveInlineIcon,
    type RichTextValueLike,
} from './value';

const ANCHOR_SETTINGS = {
    name: FORMAT_NAME,
    tagName: 'span',
    className: INLINE_ICON_CLASS,
} as const;

interface FormatEditProps {
    readonly value: RichTextValueLike;
    readonly onChange: ( value: RichTextValueLike ) => void;
    readonly isObjectActive?: boolean;
    readonly contentRef?: RefObject< HTMLElement >;
}

/** Read the active icon's replacement object, if the caret is on one. */
function activeIconObject( props: FormatEditProps ): InlineIconObject | undefined {
    if ( ! props.isObjectActive ) {
        return undefined;
    }
    const index  = props.value.start ?? 0;
    const object = props.value.replacements[ index ] as InlineIconObject | undefined;
    return object && FORMAT_NAME === object.type ? object : undefined;
}

/**
 * Read an object attribute by its schema key, falling back to the raw
 * HTML attribute. A freshly inserted object is keyed by schema key, but
 * be defensive about a reloaded object that surfaced the attribute under
 * its HTML name.
 */
function readAttr(
    attrs: Record< string, string > | undefined,
    schemaKey: string,
    htmlKey: string,
): string | undefined {
    return attrs?.[ schemaKey ] ?? attrs?.[ htmlKey ];
}

/** Derive the popover's inherit-first controls from an active icon. */
function optionsFromObject( object: InlineIconObject | undefined ): InlineIconOptions {
    if ( ! object ) {
        return {};
    }
    return {
        ...parseInlineIconStyle( readAttr( object.attributes, 'style', 'style' ) ),
        label: readAttr( object.attributes, 'label', 'aria-label' ),
    };
}

export function InlineIconEdit( props: FormatEditProps ): ReactElement {
    const { value, onChange, isObjectActive, contentRef } = props;

    const [ chooserOpen, setChooserOpen ] = useState( false );
    // `insert` drops a new icon at the caret; `replace` swaps the icon
    // already under the caret (the popover's "Replace" affordance).
    const [ mode, setMode ] = useState< 'insert' | 'replace' >( 'insert' );

    const activeObject = activeIconObject( props );
    const activeOptions = optionsFromObject( activeObject );

    const popoverAnchor = useAnchor( {
        editableContentElement: contentRef?.current ?? null,
        // `useAnchor` types `settings` as a full `WPFormat`; only the
        // tag/class matter for locating the format boundary.
        settings: ANCHOR_SETTINGS as never,
    } );

    const applyObject = ( object: InlineIconObject ): void => {
        const next =
            'replace' === mode && activeObject
                ? replaceActiveInlineIcon( value, object )
                : insertInlineIcon( value, object );
        onChange( next );
    };

    const handleLibrarySelect = async ( ref: IconRef ): Promise< void > => {
        // Preserve the size / colour / label already on the icon when
        // replacing; a fresh insert starts inherit-first.
        const options = 'replace' === mode ? activeOptions : {};
        const svg     = await fetchIconSvg( ref.set, ref.name );
        applyObject( buildSetIconObject( ref, svg, options ) );
        closeChooser();
    };

    const handleCustomApplied = ( sanitized: string ): void => {
        if ( '' === sanitized.trim() ) {
            return;
        }
        const options = 'replace' === mode ? activeOptions : {};
        applyObject( buildCustomSvgObject( sanitized, options ) );
        closeChooser();
    };

    const closeChooser = (): void => {
        setChooserOpen( false );
        setMode( 'insert' );
    };

    const openInsert = (): void => {
        setMode( 'insert' );
        setChooserOpen( true );
    };

    const openReplace = (): void => {
        setMode( 'replace' );
        setChooserOpen( true );
    };

    // Rebuild the active icon in place with new size / colour / label,
    // preserving its set reference (or custom SVG) and preview body.
    const updateOptions = ( next: InlineIconOptions ): void => {
        if ( ! activeObject ) {
            return;
        }
        const attrs    = activeObject.attributes ?? {};
        const iconSet  = readAttr( attrs, 'iconSet', 'data-icon-set' );
        const iconName = readAttr( attrs, 'iconName', 'data-icon-name' );
        const base: Record< string, string > =
            iconSet && iconName ? { iconSet, iconName } : {};
        const object: InlineIconObject = {
            type: FORMAT_NAME,
            attributes: buildInlineIconAttributes( base, next ),
            innerHTML: activeObject.innerHTML ?? '',
        };
        onChange( replaceActiveInlineIcon( value, object ) );
    };

    const removeIcon = (): void => {
        onChange( removeActiveInlineIcon( value ) );
    };

    return (
        <>
            <RichTextToolbarButton
                icon={ <IconInserterIcon /> }
                title={ __( 'Insert icon', TEXT_DOMAIN ) }
                onClick={ openInsert }
                isActive={ !! isObjectActive }
                role="menuitemcheckbox"
            />

            { chooserOpen && (
                <Modal
                    title={
                        'replace' === mode
                            ? __( 'Replace icon', TEXT_DOMAIN )
                            : __( 'Insert icon', TEXT_DOMAIN )
                    }
                    onRequestClose={ closeChooser }
                    className="ap-inline-icon__chooser"
                    size="medium"
                >
                    <TabPanel
                        className="ap-inline-icon__chooser-tabs"
                        tabs={ [
                            { name: 'library', title: __( 'Icon library', TEXT_DOMAIN ) },
                            { name: 'custom', title: __( 'Custom SVG', TEXT_DOMAIN ) },
                        ] }
                    >
                        { ( tab ) =>
                            'custom' === tab.name ? (
                                <CustomSvgControl
                                    customSvg=""
                                    onApplied={ handleCustomApplied }
                                    onCleared={ () => undefined }
                                />
                            ) : (
                                <IconPickerPanel
                                    onSelect={ handleLibrarySelect }
                                    onClose={ closeChooser }
                                />
                            )
                        }
                    </TabPanel>
                </Modal>
            ) }

            { isObjectActive && activeObject && ! chooserOpen && (
                <Popover
                    anchor={ popoverAnchor }
                    className="ap-inline-icon__popover"
                    focusOnMount={ false }
                    placement="bottom"
                >
                    <div className="ap-inline-icon__popover-body" style={ { minWidth: '240px', padding: '8px' } }>
                        <RangeControl
                            label={ __( 'Size', TEXT_DOMAIN ) }
                            help={ __( 'Scales the icon relative to the text. Leave blank to inherit.', TEXT_DOMAIN ) }
                            value={ activeOptions.sizeEm }
                            onChange={ ( size ) =>
                                updateOptions( {
                                    ...activeOptions,
                                    sizeEm: 'number' === typeof size ? size : undefined,
                                } )
                            }
                            min={ 0.5 }
                            max={ 4 }
                            step={ 0.25 }
                            allowReset
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                        />

                        <p style={ { margin: '8px 0 4px', fontSize: '11px', textTransform: 'uppercase', opacity: 0.7 } }>
                            { __( 'Color', TEXT_DOMAIN ) }
                        </p>
                        <ColorPalette
                            value={ activeOptions.color }
                            colors={ [] }
                            onChange={ ( color ) =>
                                updateOptions( { ...activeOptions, color: color ?? undefined } )
                            }
                            clearable
                            __experimentalIsRenderedInSidebar={ false }
                        />

                        <TextControl
                            label={ __( 'Accessible label', TEXT_DOMAIN ) }
                            help={ __( 'Leave blank for a decorative icon (hidden from screen readers).', TEXT_DOMAIN ) }
                            value={ activeOptions.label ?? '' }
                            onChange={ ( label ) =>
                                updateOptions( {
                                    ...activeOptions,
                                    label: '' === label.trim() ? undefined : label,
                                } )
                            }
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                        />

                        <Flex justify="flex-start" gap={ 2 } style={ { marginTop: '12px' } }>
                            <FlexItem>
                                <Button variant="secondary" size="small" onClick={ openReplace }>
                                    { __( 'Replace', TEXT_DOMAIN ) }
                                </Button>
                            </FlexItem>
                            <FlexItem>
                                <Button variant="secondary" isDestructive size="small" onClick={ removeIcon }>
                                    { __( 'Remove', TEXT_DOMAIN ) }
                                </Button>
                            </FlexItem>
                        </Flex>
                    </div>
                </Popover>
            ) }
        </>
    );
}

export default InlineIconEdit;
