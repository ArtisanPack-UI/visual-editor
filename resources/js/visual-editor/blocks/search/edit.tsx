/**
 * Search — edit component.
 *
 * Ported from `@wordpress/block-library/src/search/edit.js` (v9.43.0) to
 * TypeScript under the `artisanpack/search` namespace. The upstream
 * `useToolsPanelDropdownMenuProps` hook from
 * `@wordpress/block-library/src/utils/hooks` is a block-library internal
 * not exposed via the package's `exports` field, so it is dropped and the
 * ToolsPanel falls back to its default dropdown behaviour. The icon button
 * always carries an `aria-label` (button text, then label, then "Search")
 * so the editor preview matches the accessible front-end markup the
 * renderers emit (carries forward the #338 fix). Native branches are not
 * carried — this fork is web-only.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    useBlockProps,
    InspectorControls,
    RichText,
    store as blockEditorStore,
    __experimentalGetElementClassName,
    useSettings,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseBorderProps as useBorderProps,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseColorProps as useColorProps,
    getTypographyClassesAndStyles as useTypographyProps,
} from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import {
    SelectControl,
    ToggleControl,
    ResizableBox,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseCustomUnits as useCustomUnits,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUnitControl as UnitControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToggleGroupControl as ToggleGroupControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanel as ToolsPanel,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanelItem as ToolsPanelItem,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalVStack as VStack,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { Icon, search } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { __unstableStripHTML as stripHTML } from '@wordpress/dom';

import {
    PC_WIDTH_DEFAULT,
    PX_WIDTH_DEFAULT,
    MIN_WIDTH,
    isPercentageUnit,
} from './constants';

// Used to calculate border radius adjustment to avoid "fat" corners when
// the button is placed inside the wrapper.
const DEFAULT_INNER_PADDING = '4px';
const PERCENTAGE_WIDTHS = [ 25, 50, 75, 100 ];

interface SearchAttributes {
    readonly label?: string;
    readonly showLabel?: boolean;
    readonly placeholder?: string;
    readonly width?: number;
    readonly widthUnit?: string;
    readonly align?: string;
    readonly buttonText?: string;
    readonly buttonPosition?: string;
    readonly buttonUseIcon?: boolean;
    readonly isSearchFieldHidden?: boolean;
    readonly style?: { border?: { radius?: number | object } };
    readonly [key: string]: unknown;
}

interface SearchEditProps {
    readonly className?: string;
    readonly attributes: SearchAttributes;
    readonly setAttributes: ( attrs: Partial<SearchAttributes> ) => void;
    readonly toggleSelection?: ( value: boolean ) => void;
    readonly isSelected?: boolean;
    readonly clientId?: string;
}

export default function SearchEdit( {
    className,
    attributes,
    setAttributes,
    toggleSelection,
    isSelected,
    clientId,
}: SearchEditProps ): ReactElement {
    const {
        label,
        showLabel,
        placeholder,
        width,
        widthUnit,
        align,
        buttonText,
        buttonPosition,
        buttonUseIcon,
        isSearchFieldHidden,
        style,
    } = attributes;

    const wasJustInsertedIntoNavigationBlock = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ( select: any ) => {
            const { getBlockParentsByBlockName, wasBlockJustInserted } =
                select( blockEditorStore );
            return (
                !! getBlockParentsByBlockName( clientId, 'core/navigation' )
                    ?.length && wasBlockJustInserted( clientId )
            );
        },
        [ clientId ]
    );
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const { __unstableMarkNextChangeAsNotPersistent } = useDispatch(
        blockEditorStore
    ) as any;

    useEffect( () => {
        if ( wasJustInsertedIntoNavigationBlock ) {
            // This side-effect should not create an undo level.
            __unstableMarkNextChangeAsNotPersistent();
            setAttributes( {
                showLabel: false,
                buttonUseIcon: true,
                buttonPosition: 'button-inside',
            } );
        }
    }, [
        __unstableMarkNextChangeAsNotPersistent,
        wasJustInsertedIntoNavigationBlock,
        setAttributes,
    ] );

    const borderRadius = style?.border?.radius;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    let borderProps = ( useBorderProps as any )( attributes );

    // Check for old deprecated numerical border radius. Done as a separate
    // check so that a borderRadius style won't overwrite the longhand
    // per-corner styles.
    if ( typeof borderRadius === 'number' ) {
        borderProps = {
            ...borderProps,
            style: {
                ...borderProps.style,
                borderRadius: `${ borderRadius }px`,
            },
        };
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const colorProps = ( useColorProps as any )( attributes );
    const [ fluidTypographySettings, layout ] = useSettings(
        'typography.fluid',
        'layout'
    );
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const typographyProps = ( useTypographyProps as any )( attributes, {
        typography: {
            fluid: fluidTypographySettings,
        },
        layout: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            wideSize: ( layout as any )?.wideSize,
        },
    } );
    const unitControlInstanceId = useInstanceId( UnitControl );
    const unitControlInputId = `wp-block-search__width-${ unitControlInstanceId }`;
    const isButtonPositionInside = 'button-inside' === buttonPosition;
    const isButtonPositionOutside = 'button-outside' === buttonPosition;
    const hasNoButton = 'no-button' === buttonPosition;
    const hasOnlyButton = 'button-only' === buttonPosition;
    const searchFieldRef = useRef<HTMLInputElement>( null );
    const buttonRef = useRef<HTMLButtonElement>( null );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const units = ( useCustomUnits as any )( {
        availableUnits: [ '%', 'px' ],
        defaultValues: { '%': PC_WIDTH_DEFAULT, px: PX_WIDTH_DEFAULT },
    } );

    useEffect( () => {
        if ( hasOnlyButton && ! isSelected ) {
            setAttributes( {
                isSearchFieldHidden: true,
            } );
        }
    }, [ hasOnlyButton, isSelected, setAttributes ] );

    // Show the search field when width changes.
    useEffect( () => {
        if ( ! hasOnlyButton || ! isSelected ) {
            return;
        }

        setAttributes( {
            isSearchFieldHidden: false,
        } );
    }, [ hasOnlyButton, isSelected, setAttributes, width ] );

    const getBlockClassNames = (): string => {
        return clsx(
            className,
            isButtonPositionInside
                ? 'wp-block-search__button-inside'
                : undefined,
            isButtonPositionOutside
                ? 'wp-block-search__button-outside'
                : undefined,
            hasNoButton ? 'wp-block-search__no-button' : undefined,
            hasOnlyButton ? 'wp-block-search__button-only' : undefined,
            ! buttonUseIcon && ! hasNoButton
                ? 'wp-block-search__text-button'
                : undefined,
            buttonUseIcon && ! hasNoButton
                ? 'wp-block-search__icon-button'
                : undefined,
            hasOnlyButton && isSearchFieldHidden
                ? 'wp-block-search__searchfield-hidden'
                : undefined
        );
    };

    const buttonPositionControls = [
        {
            label: __( 'Button outside' ),
            value: 'button-outside',
        },
        {
            label: __( 'Button inside' ),
            value: 'button-inside',
        },
        {
            label: __( 'No button' ),
            value: 'no-button',
        },
        {
            label: __( 'Button only' ),
            value: 'button-only',
        },
    ];

    const getResizableSides = (): Record< string, boolean > => {
        if ( hasOnlyButton ) {
            return {};
        }

        return {
            right: align !== 'right',
            left: align === 'right',
        };
    };

    const renderTextField = (): ReactElement => {
        // If the input is inside the wrapper, the wrapper gets the border
        // color styles/classes, not the input control.
        const textFieldClasses = clsx(
            'wp-block-search__input',
            isButtonPositionInside ? undefined : borderProps.className,
            typographyProps.className
        );
        const textFieldStyles = {
            ...( isButtonPositionInside
                ? {
                      borderRadius: borderProps.style?.borderRadius,
                      borderTopLeftRadius:
                          borderProps.style?.borderTopLeftRadius,
                      borderTopRightRadius:
                          borderProps.style?.borderTopRightRadius,
                      borderBottomLeftRadius:
                          borderProps.style?.borderBottomLeftRadius,
                      borderBottomRightRadius:
                          borderProps.style?.borderBottomRightRadius,
                  }
                : borderProps.style ),
            ...typographyProps.style,
            textDecoration: undefined,
        };

        return (
            <input
                type="search"
                className={ textFieldClasses }
                style={ textFieldStyles }
                aria-label={ __( 'Optional placeholder text' ) }
                // We hide the placeholder field's placeholder when there is a
                // value. This stops screen readers from reading the
                // placeholder field's placeholder which is confusing.
                placeholder={
                    placeholder ? undefined : __( 'Optional placeholder…' )
                }
                value={ placeholder }
                onChange={ ( event ) =>
                    setAttributes( { placeholder: event.target.value } )
                }
                ref={ searchFieldRef }
            />
        );
    };

    const renderButton = (): ReactElement => {
        // If the button is inside the wrapper, the wrapper gets the border
        // color styles/classes, not the button.
        const buttonClasses = clsx(
            'wp-block-search__button',
            colorProps.className,
            typographyProps.className,
            isButtonPositionInside ? undefined : borderProps.className,
            buttonUseIcon ? 'has-icon' : undefined,
            __experimentalGetElementClassName( 'button' )
        );
        const buttonStyles = {
            ...colorProps.style,
            ...typographyProps.style,
            ...( isButtonPositionInside
                ? {
                      borderRadius: borderProps.style?.borderRadius,
                      borderTopLeftRadius:
                          borderProps.style?.borderTopLeftRadius,
                      borderTopRightRadius:
                          borderProps.style?.borderTopRightRadius,
                      borderBottomLeftRadius:
                          borderProps.style?.borderBottomLeftRadius,
                      borderBottomRightRadius:
                          borderProps.style?.borderBottomRightRadius,
                  }
                : borderProps.style ),
        };
        const handleButtonClick = (): void => {
            if ( hasOnlyButton ) {
                setAttributes( {
                    isSearchFieldHidden: ! isSearchFieldHidden,
                } );
            }
        };

        // Accessible name precedence mirrors the front-end renderers: the
        // button text, the search label, then a hard-coded "Search". This
        // keeps the icon button labelled in the canvas just as #338 fixed it
        // for the rendered output.
        const iconButtonAriaLabel = buttonText
            ? stripHTML( buttonText )
            : label
              ? stripHTML( label )
              : __( 'Search' );

        return (
            <>
                { buttonUseIcon && (
                    <button
                        type="button"
                        className={ buttonClasses }
                        style={ buttonStyles }
                        aria-label={ iconButtonAriaLabel }
                        onClick={ handleButtonClick }
                        ref={ buttonRef }
                    >
                        <Icon icon={ search } />
                    </button>
                ) }

                { ! buttonUseIcon && (
                    <RichText
                        identifier="buttonText"
                        className={ buttonClasses }
                        style={ buttonStyles }
                        aria-label={ __( 'Button text' ) }
                        placeholder={ __( 'Add button text…' ) }
                        withoutInteractiveFormatting
                        value={ buttonText }
                        onChange={ ( html: string ) =>
                            setAttributes( { buttonText: html } )
                        }
                        onClick={ handleButtonClick }
                    />
                ) }
            </>
        );
    };

    const controls = (
        <InspectorControls>
            <ToolsPanel
                label={ __( 'Settings' ) }
                resetAll={ () => {
                    setAttributes( {
                        width: undefined,
                        widthUnit: undefined,
                        showLabel: true,
                        buttonUseIcon: false,
                        buttonPosition: 'button-outside',
                        isSearchFieldHidden: false,
                    } );
                } }
            >
                <ToolsPanelItem
                    hasValue={ () => ! showLabel }
                    label={ __( 'Show label' ) }
                    onDeselect={ () => {
                        setAttributes( {
                            showLabel: true,
                        } );
                    } }
                    isShownByDefault
                >
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        checked={ showLabel }
                        label={ __( 'Show label' ) }
                        onChange={ ( value ) =>
                            setAttributes( {
                                showLabel: value,
                            } )
                        }
                    />
                </ToolsPanelItem>
                <ToolsPanelItem
                    hasValue={ () => buttonPosition !== 'button-outside' }
                    label={ __( 'Button position' ) }
                    onDeselect={ () => {
                        setAttributes( {
                            buttonPosition: 'button-outside',
                            isSearchFieldHidden: false,
                        } );
                    } }
                    isShownByDefault
                >
                    <SelectControl
                        value={ buttonPosition }
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        label={ __( 'Button position' ) }
                        onChange={ ( value: string ) => {
                            setAttributes( {
                                buttonPosition: value,
                                isSearchFieldHidden: value === 'button-only',
                            } );
                        } }
                        options={ buttonPositionControls }
                    />
                </ToolsPanelItem>
                { buttonPosition !== 'no-button' && (
                    <ToolsPanelItem
                        hasValue={ () => !! buttonUseIcon }
                        label={ __( 'Use button with icon' ) }
                        onDeselect={ () => {
                            setAttributes( {
                                buttonUseIcon: false,
                            } );
                        } }
                        isShownByDefault
                    >
                        <ToggleControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            checked={ buttonUseIcon }
                            label={ __( 'Use button with icon' ) }
                            onChange={ ( value ) =>
                                setAttributes( {
                                    buttonUseIcon: value,
                                } )
                            }
                        />
                    </ToolsPanelItem>
                ) }
                <ToolsPanelItem
                    hasValue={ () => !! width }
                    label={ __( 'Width' ) }
                    onDeselect={ () => {
                        setAttributes( {
                            width: undefined,
                            widthUnit: undefined,
                        } );
                    } }
                    isShownByDefault
                >
                    <VStack>
                        <UnitControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            label={ __( 'Width' ) }
                            id={ unitControlInputId }
                            min={ isPercentageUnit( widthUnit ) ? 0 : MIN_WIDTH }
                            max={
                                isPercentageUnit( widthUnit ) ? 100 : undefined
                            }
                            step={ 1 }
                            onChange={ ( newWidth: string ) => {
                                const parsedNewWidth =
                                    newWidth === ''
                                        ? undefined
                                        : parseInt( newWidth, 10 );
                                setAttributes( {
                                    width: parsedNewWidth,
                                } );
                            } }
                            onUnitChange={ ( newUnit: string ) => {
                                setAttributes( {
                                    width:
                                        '%' === newUnit
                                            ? PC_WIDTH_DEFAULT
                                            : PX_WIDTH_DEFAULT,
                                    widthUnit: newUnit,
                                } );
                            } }
                            __unstableInputWidth="80px"
                            value={ `${ width }${ widthUnit }` }
                            units={ units }
                        />
                        <ToggleGroupControl
                            label={ __( 'Percentage Width' ) }
                            value={
                                width !== undefined &&
                                PERCENTAGE_WIDTHS.includes( width ) &&
                                widthUnit === '%'
                                    ? width
                                    : undefined
                            }
                            hideLabelFromVision
                            onChange={ ( newWidth: number ) => {
                                setAttributes( {
                                    width: newWidth,
                                    widthUnit: '%',
                                } );
                            } }
                            isBlock
                            __next40pxDefaultSize
                        >
                            { PERCENTAGE_WIDTHS.map( ( widthValue ) => {
                                return (
                                    <ToggleGroupControlOption
                                        key={ widthValue }
                                        value={ widthValue }
                                        label={ sprintf(
                                            /* translators: %d: Percentage value. */
                                            __( '%d%%' ),
                                            widthValue
                                        ) }
                                    />
                                );
                            } ) }
                        </ToggleGroupControl>
                    </VStack>
                </ToolsPanelItem>
            </ToolsPanel>
        </InspectorControls>
    );

    const isNonZeroBorderRadius = ( radius: unknown ): boolean =>
        radius !== undefined && parseInt( radius as string, 10 ) !== 0;

    const padBorderRadius = ( radius: string ): string | undefined =>
        isNonZeroBorderRadius( radius )
            ? `calc(${ radius } + ${ DEFAULT_INNER_PADDING })`
            : undefined;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const getWrapperStyles = (): any => {
        const styles = isButtonPositionInside
            ? borderProps.style
            : {
                  borderRadius: borderProps.style?.borderRadius,
                  borderTopLeftRadius: borderProps.style?.borderTopLeftRadius,
                  borderTopRightRadius: borderProps.style?.borderTopRightRadius,
                  borderBottomLeftRadius:
                      borderProps.style?.borderBottomLeftRadius,
                  borderBottomRightRadius:
                      borderProps.style?.borderBottomRightRadius,
              };

        if ( isButtonPositionInside ) {
            // Button inside wrapper with a border radius value to apply. Add
            // default padding so we don't get "fat" corners. CSS calc() only
            // applies if both values have units.
            if ( typeof borderRadius === 'object' ) {
                const {
                    borderTopLeftRadius,
                    borderTopRightRadius,
                    borderBottomLeftRadius,
                    borderBottomRightRadius,
                } = borderProps.style;

                return {
                    ...styles,
                    borderTopLeftRadius: padBorderRadius( borderTopLeftRadius ),
                    borderTopRightRadius:
                        padBorderRadius( borderTopRightRadius ),
                    borderBottomLeftRadius: padBorderRadius(
                        borderBottomLeftRadius
                    ),
                    borderBottomRightRadius: padBorderRadius(
                        borderBottomRightRadius
                    ),
                };
            }

            const radius = Number.isInteger( borderRadius )
                ? `${ borderRadius }px`
                : borderRadius;

            styles.borderRadius = `calc(${ radius } + ${ DEFAULT_INNER_PADDING })`;
        }

        return styles;
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( {
        className: getBlockClassNames(),
        style: {
            ...typographyProps.style,
            // Input opts out of text decoration.
            textDecoration: undefined,
        },
    } );

    const labelClassnames = clsx(
        'wp-block-search__label',
        typographyProps.className
    );

    return (
        <div { ...blockProps }>
            { controls }

            { showLabel && (
                <RichText
                    identifier="label"
                    className={ labelClassnames }
                    aria-label={ __( 'Label text' ) }
                    placeholder={ __( 'Add label…' ) }
                    withoutInteractiveFormatting
                    value={ label }
                    onChange={ ( html: string ) =>
                        setAttributes( { label: html } )
                    }
                    style={ typographyProps.style }
                />
            ) }

            <ResizableBox
                size={ {
                    width:
                        width === undefined
                            ? 'auto'
                            : `${ width }${ widthUnit }`,
                    height: 'auto',
                } }
                className={ clsx(
                    'wp-block-search__inside-wrapper',
                    isButtonPositionInside ? borderProps.className : undefined
                ) }
                style={ getWrapperStyles() }
                minWidth={ MIN_WIDTH }
                enable={ getResizableSides() }
                onResizeStart={ (
                    _event: unknown,
                    _direction: unknown,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    elt: any
                ) => {
                    setAttributes( {
                        width: parseInt( elt.offsetWidth, 10 ),
                        widthUnit: 'px',
                    } );
                    toggleSelection?.( false );
                } }
                onResizeStop={ (
                    _event: unknown,
                    _direction: unknown,
                    _elt: unknown,
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    delta: any
                ) => {
                    setAttributes( {
                        width: parseInt(
                            `${ ( width ?? 0 ) + delta.width }`,
                            10
                        ),
                    } );
                    toggleSelection?.( true );
                } }
                showHandle={ isSelected }
            >
                { ( isButtonPositionInside ||
                    isButtonPositionOutside ||
                    hasOnlyButton ) && (
                    <>
                        { renderTextField() }
                        { renderButton() }
                    </>
                ) }

                { hasNoButton && renderTextField() }
            </ResizableBox>
        </div>
    );
}
