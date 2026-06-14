/**
 * Phase 5 (#556) — paste flow updates `customSvg` correctly.
 *
 * We mock `@wordpress/components` down to plain DOM primitives so we can
 * fire `change` / `blur` events without the real Notice / PanelBody
 * machinery in the way. The `fetchImpl` prop is the seam for the
 * sanitize endpoint — the test never touches `window.fetch`.
 */

import { describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';

vi.mock( '@wordpress/i18n', () => ( {
    __: ( s: string ) => s,
    sprintf: ( fmt: string, ...args: unknown[] ) =>
        fmt.replace( /%d/g, () => String( args.shift() ) ),
} ) );

vi.mock( '@wordpress/components', () => ( {
    PanelBody: ( { children, title }: { children: React.ReactNode; title: string } ) => (
        <section aria-label={ title }>{ children }</section>
    ),
    Button: ( {
        children,
        onClick,
        disabled,
        variant,
    }: {
        children: React.ReactNode;
        onClick?: () => void;
        disabled?: boolean;
        variant?: string;
    } ) => (
        <button
            type="button"
            onClick={ onClick }
            disabled={ disabled }
            data-variant={ variant }
        >
            { children }
        </button>
    ),
    Notice: ( {
        children,
        status,
    }: {
        children: React.ReactNode;
        status: string;
    } ) => <div role={ status === 'error' ? 'alert' : 'status' }>{ children }</div>,
    TextareaControl: ( {
        value,
        onChange,
        onBlur,
        label,
    }: {
        value: string;
        onChange: ( v: string ) => void;
        onBlur?: () => void;
        label: string;
        rows?: number;
        help?: string;
    } ) => (
        <label>
            { label }
            <textarea
                aria-label={ label }
                value={ value }
                onChange={ ( event ) => onChange( event.target.value ) }
                onBlur={ onBlur }
            />
        </label>
    ),
} ) );

import CustomSvgControl from '../custom-svg-control';

function makeFetch(
    body: { svg: string; warnings: readonly string[] },
    status = 200,
): typeof fetch {
    return vi.fn( async () =>
        ( {
            ok: status >= 200 && status < 300,
            status,
            json: async () => body,
        } ) as unknown as Response,
    ) as unknown as typeof fetch;
}

describe( 'CustomSvgControl paste flow', () => {
    it( 'updates customSvg with the server-sanitized result on textarea blur', async () => {
        const onApplied = vi.fn();
        const onCleared = vi.fn();
        const sanitizedSvg = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';
        const fetchImpl = makeFetch( {
            svg: sanitizedSvg,
            warnings: [ 'removed <script> element' ],
        } );

        render(
            <CustomSvgControl
                customSvg=""
                onApplied={ onApplied }
                onCleared={ onCleared }
                fetchImpl={ fetchImpl }
            />,
        );

        const textarea = screen.getByLabelText( 'Paste SVG markup' ) as HTMLTextAreaElement;

        await act( async () => {
            fireEvent.change( textarea, {
                target: {
                    value: '<svg><script>alert(1)</script><path d="M0 0"/></svg>',
                },
            } );
        } );

        await act( async () => {
            fireEvent.blur( textarea );
        } );

        expect( fetchImpl ).toHaveBeenCalledTimes( 1 );
        const [ url, init ] = ( fetchImpl as ReturnType< typeof vi.fn > ).mock.calls[ 0 ] as [
            string,
            RequestInit,
        ];
        expect( url ).toBe( '/visual-editor/api/icons/svg/sanitize' );
        expect( init.method ).toBe( 'POST' );
        expect( init.body ).toBeTypeOf( 'string' );

        // The raw paste is sent verbatim — the server is the authority
        // and reports back exactly what it stripped, so the warning
        // panel matches reality.
        const sent = JSON.parse( String( init.body ) ) as { svg: string };
        expect( sent.svg ).toContain( '<script' );

        expect( onApplied ).toHaveBeenCalledTimes( 1 );
        expect( onApplied ).toHaveBeenCalledWith( sanitizedSvg, [
            'removed <script> element',
        ] );
        expect( onCleared ).not.toHaveBeenCalled();

        // Warning surfaced inline.
        expect(
            screen.getByText( /Some content was removed/i ),
        ).toBeTruthy();
    } );

    it( 'clears customSvg when the textarea is emptied and blurred', async () => {
        const onApplied = vi.fn();
        const onCleared = vi.fn();
        const fetchImpl = makeFetch( { svg: '', warnings: [] } );

        render(
            <CustomSvgControl
                customSvg='<svg><path d="M0 0"/></svg>'
                onApplied={ onApplied }
                onCleared={ onCleared }
                fetchImpl={ fetchImpl }
            />,
        );

        const textarea = screen.getByLabelText( 'Paste SVG markup' ) as HTMLTextAreaElement;
        await act( async () => {
            fireEvent.change( textarea, { target: { value: '' } } );
        } );
        await act( async () => {
            fireEvent.blur( textarea );
        } );

        expect( fetchImpl ).not.toHaveBeenCalled();
        expect( onCleared ).toHaveBeenCalledTimes( 1 );
        expect( onApplied ).not.toHaveBeenCalled();
    } );

    it( 'shows an error and skips the upload when a non-svg file is chosen', async () => {
        const onApplied = vi.fn();
        const onCleared = vi.fn();
        const fetchImpl = makeFetch( { svg: '', warnings: [] } );

        const { container } = render(
            <CustomSvgControl
                customSvg=""
                onApplied={ onApplied }
                onCleared={ onCleared }
                fetchImpl={ fetchImpl }
            />,
        );

        const fileInput = container.querySelector(
            'input[type="file"]',
        ) as HTMLInputElement;
        const bogus = new File( [ 'not really svg' ], 'fake.png', { type: 'image/png' } );

        await act( async () => {
            fireEvent.change( fileInput, { target: { files: [ bogus ] } } );
        } );

        expect( fetchImpl ).not.toHaveBeenCalled();
        expect( onApplied ).not.toHaveBeenCalled();
        expect( screen.getByRole( 'alert' ).textContent ).toMatch( /isn’t an SVG/i );
    } );
} );
