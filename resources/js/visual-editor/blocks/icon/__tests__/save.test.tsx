/**
 * `artisanpack/icon` save + deprecation contract (#749).
 *
 * The icon block is dynamic (server-rendered). Its `save` must return
 * `null` so only the block delimiter + attributes persist — storing real
 * markup made the block drift out of validation ("unexpected or invalid
 * content"), most visibly when nested in a group. A single deprecation
 * reproduces the pre-#749 markup so already-saved icons migrate back to a
 * valid `artisanpack/icon` instead of being downgraded to a paragraph.
 *
 * `@wordpress/block-editor` is mocked (its transitive `diff` import can't
 * resolve outside the Vite build) so `useBlockProps.save()` simply echoes
 * whatever props it is handed — enough to render the deprecation's save
 * element and assert its markup shape.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock( '@wordpress/block-editor', () => ( {
    useBlockProps: Object.assign(
        ( props?: Record< string, unknown > ) => ( { ...props } ),
        { save: ( props?: Record< string, unknown > ) => ( { ...props } ) }
    ),
} ) );

import save from '../save';
import deprecated from '../deprecated';
import type { IconAttributes } from '../types';

const attrs = ( overrides: Partial< IconAttributes > = {} ): IconAttributes =>
    ( { size: 32, sizeUnit: 'px', ...overrides } as IconAttributes );

describe( 'artisanpack/icon save', () => {
    it( 'returns null so the dynamic block persists no inner markup', () => {
        expect( save() ).toBeNull();
    } );
} );

describe( 'artisanpack/icon deprecation', () => {
    it( 'ships exactly one deprecation carrying the block.json shape', () => {
        expect( Array.isArray( deprecated ) ).toBe( true );
        expect( deprecated ).toHaveLength( 1 );
        expect( deprecated[ 0 ].attributes ).toHaveProperty( 'iconRef' );
        expect( deprecated[ 0 ].supports ).toHaveProperty( 'position' );
        expect( typeof deprecated[ 0 ].save ).toBe( 'function' );
    } );

    it( 'reproduces the legacy iconRef markup so old content still matches', () => {
        const html = renderToStaticMarkup(
            deprecated[ 0 ].save( {
                attributes: attrs( { iconRef: { set: 'fa', name: 'star' } } ),
            } )
        );

        // The outer wrapper class is injected by `useBlockProps.save()` in
        // production; the mock here echoes empty props, so assert the body
        // span the deprecation is responsible for reproducing.
        expect( html ).toContain( 'wp-block-artisanpack-icon__ref' );
        expect( html ).toContain( 'data-icon-set="fa"' );
        expect( html ).toContain( 'data-icon-name="star"' );
    } );

    it( 'reproduces the legacy customSvg markup', () => {
        const html = renderToStaticMarkup(
            deprecated[ 0 ].save( {
                attributes: attrs( { customSvg: '<svg><path d="M0 0"/></svg>' } ),
            } )
        );

        expect( html ).toContain( 'wp-block-artisanpack-icon__svg' );
        expect( html ).toContain( '<svg>' );
    } );

    it( 'reproduces the legacy placeholder markup when no icon is set', () => {
        const html = renderToStaticMarkup(
            deprecated[ 0 ].save( { attributes: attrs() } )
        );

        expect( html ).toContain( 'wp-block-artisanpack-icon__placeholder' );
        expect( html ).toContain( 'aria-hidden="true"' );
    } );

    it( 'wraps the legacy markup in a link when a safe link is set', () => {
        const html = renderToStaticMarkup(
            deprecated[ 0 ].save( {
                attributes: attrs( {
                    iconRef: { set: 'fa', name: 'star' },
                    link: 'https://example.com',
                    linkTarget: '_blank',
                } ),
            } )
        );

        expect( html ).toContain( '<a' );
        expect( html ).toContain( 'href="https://example.com"' );
        expect( html ).toContain( 'rel="noopener noreferrer"' );
    } );
} );
