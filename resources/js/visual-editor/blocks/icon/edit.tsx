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
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { sanitizeOnServer } from './custom-svg';
import CustomSvgControl from './custom-svg-control';
import IconPicker from './icon-picker';
import type { IconAttributes, IconRef } from './types';
import {
    composeRel,
    computeIconStyle,
    computeTransform,
    hasDecorativeLinkConflict,
    normalizeAttributes,
    normalizeLinkTarget,
    shouldRenderLink,
} from './utils';

interface IconEditProps {
    readonly attributes: IconAttributes;
    readonly setAttributes: ( next: Partial< IconAttributes > ) => void;
}

export default function IconEdit( { attributes, setAttributes }: IconEditProps ): ReactElement {
    const normalized = normalizeAttributes( attributes );
    const blockProps = useBlockProps();
    const [ pickerOpen, setPickerOpen ] = useState( false );

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
    const wrapped = shouldRenderLink( normalized ) ? (
        <a
            href={ normalized.link }
            target={ normalizeLinkTarget( normalized.linkTarget ) || undefined }
            rel={ composeRel( normalized.linkTarget, normalized.linkRel ) || undefined }
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
