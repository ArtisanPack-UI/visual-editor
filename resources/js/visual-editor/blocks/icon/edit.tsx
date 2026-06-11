/**
 * Icon — editor-side render.
 *
 * Phase 1 (#552). The picker, paste-svg, and admin-upload phases plug
 * additional controls in via the inspector — this file ships the
 * canvas render + a placeholder picker button so authors can verify
 * the block exists end-to-end.
 */

import { useEffect, useState } from 'react';
import type { ReactElement } from 'react';
import {
    InspectorControls,
    PanelColorSettings,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Button,
    PanelBody,
    PanelRow,
    // eslint-disable-next-line @typescript-eslint/naming-convention -- WP component name
    __experimentalNumberControl as NumberControl,
    // eslint-disable-next-line @typescript-eslint/naming-convention -- WP component name
    __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { sanitizeOnServer } from './custom-svg';
import CustomSvgControl from './custom-svg-control';
import IconPicker from './icon-picker';
import type { IconAttributes, IconRef, SizeUnit } from './types';
import {
    composeRel,
    computeIconStyle,
    computeTransform,
    hasDecorativeLinkConflict,
    normalizeAttributes,
    normalizeLinkTarget,
    shouldRenderLink,
} from './utils';

const SIZE_UNIT_OPTIONS: ReadonlyArray< { value: SizeUnit; label: string } > = [
    { value: 'px', label: 'px' },
    { value: 'em', label: 'em' },
    { value: 'rem', label: 'rem' },
    { value: '%', label: '%' },
    { value: 'vw', label: 'vw' },
    { value: 'vh', label: 'vh' },
];

const ALLOWED_UNITS: ReadonlySet< SizeUnit > = new Set(
    SIZE_UNIT_OPTIONS.map( ( option ) => option.value ),
);

interface ParsedUnitValue {
    readonly value: number | null;
    readonly unit: SizeUnit | null;
}

/**
 * Parse the `'48px'` / `'1.5em'` strings that `UnitControl` emits.
 *
 * Returns nulls (not zeros) when either component is missing so the
 * caller can persist "author cleared the field" as a distinct state
 * from "author typed 0" — `width: null` falls back to `size`, while
 * `width: 0` would render a zero-size box. Negative inputs are
 * rejected outright so authors can't dial in a negative dimension
 * that would clamp into 1 on the server but render as 0 in the
 * canvas mid-flight.
 */
function parseUnitValue( raw: string | undefined | null ): ParsedUnitValue {
    if ( typeof raw !== 'string' ) {
        return { value: null, unit: null };
    }
    const trimmed = raw.trim();
    if ( trimmed.length === 0 ) {
        return { value: null, unit: null };
    }
    const match = trimmed.match( /^(\d+(?:\.\d+)?)([a-z%]+)?$/i );
    if ( ! match ) {
        return { value: null, unit: null };
    }
    const value = Number.parseFloat( match[ 1 ] );
    if ( ! Number.isFinite( value ) || value < 0 ) {
        return { value: null, unit: null };
    }
    const unitRaw = ( match[ 2 ] ?? 'px' ).toLowerCase() as SizeUnit;
    const unit = ALLOWED_UNITS.has( unitRaw ) ? unitRaw : 'px';
    return { value, unit };
}

interface IconEditProps {
    readonly attributes: IconAttributes;
    readonly setAttributes: ( next: Partial< IconAttributes > ) => void;
}

export default function IconEdit( { attributes, setAttributes }: IconEditProps ): ReactElement {
    const normalized = normalizeAttributes( attributes );
    // No wrapper style is computed here — `useBlockProps()` already
    // injects WP-managed background/border/padding/margin onto the
    // wrapper div via the block.json `supports` map. See utils.ts.
    const blockProps = useBlockProps();
    const [ pickerOpen, setPickerOpen ] = useState( false );

    const widthDisplay  = normalized.widthExplicit  ? `${ normalized.width }${ normalized.widthUnit }`   : '';
    const heightDisplay = normalized.heightExplicit ? `${ normalized.height }${ normalized.heightUnit }` : '';

    const onSizeChange = ( next: string | number | undefined ): void => {
        // NumberControl emits `string | number | undefined`. Coerce
        // through `Number.parseFloat` on string input so the size
        // attribute stays a number; skip the update when the input is
        // cleared so the old value is preserved rather than persisting
        // `NaN`.
        let parsed: number;
        if ( typeof next === 'number' ) {
            parsed = next;
        } else if ( typeof next === 'string' ) {
            parsed = Number.parseFloat( next );
        } else {
            return;
        }
        if ( ! Number.isFinite( parsed ) ) {
            return;
        }
        setAttributes( { size: parsed } );
    };

    const onWidthChange = ( next: string | undefined ): void => {
        const parsed = parseUnitValue( next );
        if ( parsed.value === null ) {
            setAttributes( { width: null, widthUnit: null } );
            return;
        }
        setAttributes( { width: parsed.value, widthUnit: parsed.unit ?? 'px' } );
    };

    const onHeightChange = ( next: string | undefined ): void => {
        const parsed = parseUnitValue( next );
        if ( parsed.value === null ) {
            setAttributes( { height: null, heightUnit: null } );
            return;
        }
        setAttributes( { height: parsed.value, heightUnit: parsed.unit ?? 'px' } );
    };

    const onIconColorChange = ( next: string | undefined ): void => {
        setAttributes( { iconColor: typeof next === 'string' ? next : '' } );
    };

    const openPicker = (): void => setPickerOpen( true );
    const closePicker = (): void => setPickerOpen( false );
    const handlePicked = ( ref: IconRef ): void => {
        // Clear `customSvg` so a previously-pasted SVG doesn't keep
        // overriding the freshly-picked iconRef on the front end.
        setAttributes( { iconRef: ref, customSvg: '' } );
    };

    // Re-sanitize the hydrated `customSvg` before rendering it into the
    // canvas. The attribute is normally written through CustomSvgControl
    // (which already routes through the sanitize endpoint), but a hand-
    // edited post DB / REST write / migration / older release without
    // Phase 5 could persist raw SVG. Re-running the sanitize endpoint
    // here closes that gap so the editor canvas never injects markup
    // that hasn't been DOM-walked by `SvgSanitizer`. The first paint
    // shows the placeholder; the sanitized markup mounts as soon as
    // the request resolves.
    const [ trustedCustomSvg, setTrustedCustomSvg ] = useState< string >( '' );
    useEffect( () => {
        if ( normalized.customSvg.trim().length === 0 ) {
            setTrustedCustomSvg( '' );
            return;
        }

        let cancelled = false;
        ( async () => {
            try {
                const { svg } = await sanitizeOnServer( normalized.customSvg );
                if ( ! cancelled ) {
                    setTrustedCustomSvg( svg );
                }
            } catch {
                if ( ! cancelled ) {
                    // Fail closed — better to show the placeholder than
                    // mount markup we couldn't verify.
                    setTrustedCustomSvg( '' );
                }
            }
        } )();

        return () => {
            cancelled = true;
        };
    }, [ normalized.customSvg ] );

    // Resolve the saved iconRef into inline SVG so the canvas mirrors
    // what the front end will render. Re-runs whenever the saved ref
    // changes (picker selection or attribute restore on reopen).
    const [ resolvedSvg, setResolvedSvg ] = useState< string | null >( null );
    const iconRefKey = normalized.iconRef
        ? `${ normalized.iconRef.set }:${ normalized.iconRef.name }`
        : null;

    useEffect( () => {
        if ( ! normalized.iconRef ) {
            setResolvedSvg( null );
            return;
        }

        let cancelled = false;
        const params = new URLSearchParams( {
            set: normalized.iconRef.set,
            name: normalized.iconRef.name,
        } );

        ( async () => {
            try {
                const response = await fetch(
                    `/visual-editor/api/icons/svg?${ params.toString() }`,
                    { headers: { Accept: 'application/json' }, credentials: 'include' }
                );
                if ( ! response.ok ) {
                    if ( ! cancelled ) {
                        setResolvedSvg( null );
                    }
                    return;
                }
                const json = ( await response.json() ) as { svg: string | null };
                if ( ! cancelled ) {
                    setResolvedSvg( json.svg ?? null );
                }
            } catch {
                if ( ! cancelled ) {
                    setResolvedSvg( null );
                }
            }
        } )();

        return () => {
            cancelled = true;
        };
    }, [ iconRefKey ] );

    const sizedStyle = computeIconStyle( normalized );
    // Only the transform belongs here — sizedStyle already carries
    // width/height. Keeping width/height: '100%' would have overridden
    // the pixel dimensions when spread after sizedStyle, breaking
    // parity with save.tsx.
    const innerStyle = {
        transform: computeTransform( normalized ),
        transformOrigin: 'center' as const,
    };

    const placeholder = (
        <button
            type="button"
            className="wp-block-artisanpack-icon__placeholder-button"
            style={ sizedStyle }
            onClick={ openPicker }
            aria-label={ __( 'Choose an icon', 'artisanpack-visual-editor' ) }
        >
            <span className="wp-block-artisanpack-icon__placeholder" aria-hidden="true" />
        </button>
    );

    let body: ReactElement = placeholder;

    if ( trustedCustomSvg.trim().length > 0 ) {
        // Phase 5 (#556): we mount the SERVER-sanitized copy of the
        // saved `customSvg`, never the raw attribute. The async
        // hydration effect above pipes `normalized.customSvg` through
        // `sanitizeOnServer` first, so even hand-edited markup gets a
        // round trip through `SvgSanitizer` before reaching the DOM
        // here. On the first paint (or if sanitization fails) the
        // placeholder shows instead — fail-closed by design.
        body = (
            <span
                className="wp-block-artisanpack-icon__svg"
                style={ { ...sizedStyle, ...innerStyle } }
                aria-hidden={ normalized.isDecorative ? 'true' : undefined }
                aria-label={
                    ! normalized.isDecorative && normalized.ariaLabel.length > 0
                        ? normalized.ariaLabel
                        : undefined
                }
                title={ normalized.titleAttr || undefined }
                dangerouslySetInnerHTML={ { __html: trustedCustomSvg } }
            />
        );
    } else if ( normalized.iconRef ) {
        body = resolvedSvg ? (
            <span
                className="wp-block-artisanpack-icon__ref"
                style={ { ...sizedStyle, ...innerStyle } }
                aria-hidden={ normalized.isDecorative ? 'true' : undefined }
                title={ normalized.titleAttr || undefined }
                // SVG comes from the package's own bundled FA Free set
                // via IconSvgResolver, which allowlists set + name and
                // file-reads from a registered directory only. Trusted
                // source, no network round-trip mutation.
                dangerouslySetInnerHTML={ { __html: resolvedSvg } }
            />
        ) : (
            <span
                className="wp-block-artisanpack-icon__ref"
                style={ { ...sizedStyle, ...innerStyle } }
                aria-hidden={ normalized.isDecorative ? 'true' : undefined }
                title={ normalized.titleAttr || undefined }
            >
                <code>
                    { normalized.iconRef.set }:{ normalized.iconRef.name }
                </code>
            </span>
        );
    }

    const conflict = hasDecorativeLinkConflict( normalized );
    // Decorative icons hide the body's ariaLabel via aria-hidden; promote
    // the label onto the anchor so the link still has an accessible name.
    const anchorAriaLabel =
        normalized.isDecorative && normalized.ariaLabel
            ? normalized.ariaLabel
            : undefined;
    const wrapped = shouldRenderLink( normalized ) ? (
        <a
            href={ normalized.link }
            target={ normalizeLinkTarget( normalized.linkTarget ) || undefined }
            rel={ composeRel( normalized.linkTarget, normalized.linkRel ) || undefined }
            aria-label={ anchorAriaLabel }
            onClick={ ( event ) => event.preventDefault() }
        >
            { body }
        </a>
    ) : (
        body
    );

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={ __( 'Icon', 'artisanpack-visual-editor' ) }
                    initialOpen={ true }
                >
                    <Button variant="secondary" onClick={ openPicker }>
                        { normalized.iconRef
                            ? __( 'Change icon…', 'artisanpack-visual-editor' )
                            : __( 'Choose icon…', 'artisanpack-visual-editor' ) }
                    </Button>
                    { normalized.iconRef && (
                        <p style={ { marginTop: '8px', fontSize: '12px', opacity: 0.75 } }>
                            <code>
                                { normalized.iconRef.set }:{ normalized.iconRef.name }
                            </code>
                        </p>
                    ) }
                </PanelBody>
                <PanelColorSettings
                    title={ __( 'Color', 'artisanpack-visual-editor' ) }
                    initialOpen={ false }
                    colorSettings={ [
                        {
                            value: normalized.iconColor || undefined,
                            onChange: onIconColorChange,
                            label: __( 'Icon', 'artisanpack-visual-editor' ),
                        },
                    ] }
                />
                <PanelBody
                    title={ __( 'Dimensions', 'artisanpack-visual-editor' ) }
                    initialOpen={ false }
                >
                    <PanelRow>
                        <NumberControl
                            label={ __( 'Size', 'artisanpack-visual-editor' ) }
                            help={ __(
                                'Sets width and height together. Override either below.',
                                'artisanpack-visual-editor',
                            ) }
                            value={ normalized.size }
                            min={ 1 }
                            max={ 1024 }
                            onChange={ onSizeChange }
                            __next40pxDefaultSize
                        />
                    </PanelRow>
                    <PanelRow>
                        <UnitControl
                            label={ __( 'Width', 'artisanpack-visual-editor' ) }
                            value={ widthDisplay }
                            units={ [ ...SIZE_UNIT_OPTIONS ] }
                            onChange={ onWidthChange }
                            placeholder={ __( 'Same as size', 'artisanpack-visual-editor' ) }
                            __next40pxDefaultSize
                        />
                    </PanelRow>
                    <PanelRow>
                        <UnitControl
                            label={ __( 'Height', 'artisanpack-visual-editor' ) }
                            value={ heightDisplay }
                            units={ [ ...SIZE_UNIT_OPTIONS ] }
                            onChange={ onHeightChange }
                            placeholder={ __( 'Same as size', 'artisanpack-visual-editor' ) }
                            __next40pxDefaultSize
                        />
                    </PanelRow>
                </PanelBody>
                <CustomSvgControl
                    customSvg={ normalized.customSvg }
                    onApplied={ ( sanitized ) => {
                        // Mirror the picker flow: setting a customSvg
                        // clears any existing iconRef so the two render
                        // paths never overlap.
                        setAttributes( { customSvg: sanitized, iconRef: null } );
                    } }
                    onCleared={ () => {
                        setAttributes( { customSvg: '' } );
                    } }
                />
            </InspectorControls>
            { pickerOpen && (
                <IconPicker onSelect={ handlePicked } onClose={ closePicker } />
            ) }
            <div { ...blockProps }>
            { wrapped }
            { conflict && (
                <span
                    role="alert"
                    className="wp-block-artisanpack-icon__a11y-warning"
                >
                    { __(
                        'A decorative icon inside a link needs an aria-label so screen readers can announce the destination.',
                        'artisanpack-visual-editor'
                    ) }
                </span>
            ) }
            </div>
        </>
    );
}
