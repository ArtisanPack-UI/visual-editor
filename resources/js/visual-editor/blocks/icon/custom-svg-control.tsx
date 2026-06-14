/**
 * Icon block — Custom SVG sidebar control.
 *
 * Phase 5 (#556). Lets authors paste raw SVG markup OR upload a `.svg`
 * file. The flow: cosmetic client-side preflight → POST to the server
 * sanitize endpoint → persist the sanitized markup into `customSvg` →
 * surface stripped-content warnings inline.
 */

import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent, ReactElement } from 'react';
import { Button, Notice, PanelBody, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { MAX_SVG_BYTES, isSvgFile, sanitizeOnServer } from './custom-svg';

interface CustomSvgControlProps {
    readonly customSvg: string;
    readonly onApplied: ( sanitized: string, warnings: readonly string[] ) => void;
    readonly onCleared: () => void;
    /**
     * Test seam — vitest replaces this with a stub so the component never
     * touches the real `window.fetch`. Production callers omit it.
     */
    readonly fetchImpl?: typeof fetch;
}

export default function CustomSvgControl( {
    customSvg,
    onApplied,
    onCleared,
    fetchImpl,
}: CustomSvgControlProps ): ReactElement {
    const [ draft, setDraft ] = useState< string >( customSvg );
    const [ warnings, setWarnings ] = useState< readonly string[] >( [] );
    const [ error, setError ] = useState< string >( '' );
    const [ pending, setPending ] = useState< boolean >( false );
    const fileInputRef = useRef< HTMLInputElement | null >( null );
    // Monotonic counter so a slow earlier sanitize response can't clobber
    // the result of a newer paste / upload. Each call to `applyRaw`
    // captures its own id; the resolution path only commits state if it
    // still matches the latest.
    const requestSeqRef = useRef< number >( 0 );

    // Resync `draft` when the prop changes from outside this component —
    // most notably when picking an icon clears `customSvg`, or when the
    // block attributes are restored on reopen. Guarded against
    // overwriting in-progress textarea edits by comparing first.
    useEffect( () => {
        setDraft( ( current ) => ( current === customSvg ? current : customSvg ) );
    }, [ customSvg ] );

    const applyRaw = async ( raw: string ): Promise< void > => {
        const requestId = ++requestSeqRef.current;
        setError( '' );
        // Send the raw paste straight to the server so the warning list
        // reflects everything the authoritative sanitizer stripped —
        // doing a client-side scrub first would silently eat the
        // `<script>` and `on*` removals the author needs to see.
        setDraft( raw );

        if ( raw.trim().length === 0 ) {
            setWarnings( [] );
            onCleared();
            return;
        }

        setPending( true );
        try {
            const result = await sanitizeOnServer( raw, fetchImpl );
            if ( requestId !== requestSeqRef.current ) {
                return;
            }
            setDraft( result.svg );
            setWarnings( result.warnings );
            if ( result.svg.trim().length === 0 ) {
                // The server rejected the markup outright (e.g. root
                // wasn't <svg>). Clear the block's customSvg so the
                // canvas falls back to the placeholder rather than
                // showing stale content.
                onCleared();
            } else {
                onApplied( result.svg, result.warnings );
            }
        } catch ( err ) {
            if ( requestId !== requestSeqRef.current ) {
                return;
            }
            setError( err instanceof Error ? err.message : String( err ) );
        } finally {
            if ( requestId === requestSeqRef.current ) {
                setPending( false );
            }
        }
    };

    const handleTextareaCommit = (): void => {
        void applyRaw( draft );
    };

    const handleFile = async ( event: ChangeEvent< HTMLInputElement > ): Promise< void > => {
        const file = event.target.files?.[ 0 ] ?? null;
        // Reset the input so re-uploading the same file fires `change` again.
        if ( fileInputRef.current ) {
            fileInputRef.current.value = '';
        }
        if ( file === null ) {
            return;
        }

        if ( ! isSvgFile( file ) ) {
            setError(
                __(
                    'That file isn’t an SVG. Pick a file ending in .svg.',
                    'artisanpack-visual-editor',
                ),
            );
            return;
        }

        if ( file.size > MAX_SVG_BYTES ) {
            setError(
                sprintf(
                    /* translators: %d is the max upload size in kilobytes. */
                    __( 'SVG is larger than the %d KB limit.', 'artisanpack-visual-editor' ),
                    Math.floor( MAX_SVG_BYTES / 1024 ),
                ),
            );
            return;
        }

        const text = await file.text();
        await applyRaw( text );
    };

    const clearSvg = (): void => {
        setDraft( '' );
        setWarnings( [] );
        setError( '' );
        onCleared();
    };

    return (
        <PanelBody
            title={ __( 'Custom SVG', 'artisanpack-visual-editor' ) }
            initialOpen={ false }
        >
            <TextareaControl
                label={ __( 'Paste SVG markup', 'artisanpack-visual-editor' ) }
                value={ draft }
                onChange={ ( value: string ) => setDraft( value ) }
                onBlur={ handleTextareaCommit }
                rows={ 6 }
                help={ __(
                    'The SVG is sanitized server-side before it’s saved.',
                    'artisanpack-visual-editor',
                ) }
            />
            <div style={ { display: 'flex', gap: '8px', marginTop: '8px' } }>
                <Button
                    variant="secondary"
                    onClick={ () => fileInputRef.current?.click() }
                    disabled={ pending }
                >
                    { __( 'Upload .svg…', 'artisanpack-visual-editor' ) }
                </Button>
                { customSvg.length > 0 && (
                    <Button variant="tertiary" onClick={ clearSvg } disabled={ pending }>
                        { __( 'Clear SVG', 'artisanpack-visual-editor' ) }
                    </Button>
                ) }
            </div>
            <input
                ref={ fileInputRef }
                type="file"
                accept="image/svg+xml,.svg"
                style={ { display: 'none' } }
                onChange={ ( event ) => {
                    void handleFile( event );
                } }
                aria-label={ __( 'Upload SVG file', 'artisanpack-visual-editor' ) }
            />
            { error !== '' && (
                <Notice status="error" isDismissible={ false }>
                    { error }
                </Notice>
            ) }
            { warnings.length > 0 && (
                <Notice status="warning" isDismissible={ false }>
                    <strong>
                        { __(
                            'Some content was removed during sanitization:',
                            'artisanpack-visual-editor',
                        ) }
                    </strong>
                    <ul className="wp-block-artisanpack-icon__svg-warnings">
                        { warnings.map( ( warning, index ) => (
                            <li key={ `${ index }-${ warning }` }>{ warning }</li>
                        ) ) }
                    </ul>
                </Notice>
            ) }
        </PanelBody>
    );
}
