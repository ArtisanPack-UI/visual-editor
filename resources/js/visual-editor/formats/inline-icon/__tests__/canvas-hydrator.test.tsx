/**
 * Placeholder inline icons in the editor canvas (#1039).
 */

import { act, render, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { resetBindingsApiConfig, setBindingsApiConfig } from '../../../bindings/config';
import {
    InlineIconCanvasHydrator,
    isInlineIconPlaceholder,
    watchInlineIconPlaceholders,
} from '../canvas-hydrator';
import { SET_ICON_PLACEHOLDER_SVG } from '../settings';

const GLYPH = '<svg viewBox="0 0 512 512"><path d="M0 0h512v512z"/></svg>';

function iconHtml( name = 'rocket', body = SET_ICON_PLACEHOLDER_SVG ): string {
    return `<span class="ap-inline-icon" data-icon-set="fas" data-icon-name="${ name }">${ body }</span>`;
}

function canvas( html: string ): HTMLElement {
    const root = document.createElement( 'div' );

    root.innerHTML = html;
    document.body.appendChild( root );

    return root;
}

afterEach( () => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    resetBindingsApiConfig();
} );

describe( 'isInlineIconPlaceholder', () => {
    it.each( [
        [ 'an empty SVG', SET_ICON_PLACEHOLDER_SVG, true ],
        [ 'no SVG', '', true ],
        [ 'a real glyph', GLYPH, false ],
    ] )( 'handles %s', ( _label, body, expected ) => {
        const root = canvas( iconHtml( 'rocket', body ) );

        expect( isInlineIconPlaceholder( root.firstElementChild as Element ) ).toBe( expected );
    } );
} );

describe( 'watchInlineIconPlaceholders', () => {
    it( 'fills a placeholder with its sized glyph', async () => {
        const root = canvas( iconHtml() );
        const stop = watchInlineIconPlaceholders( root, () => Promise.resolve( GLYPH ) );

        await waitFor( () => expect( root.querySelector( 'path' ) ).not.toBeNull() );

        expect( root.querySelector( 'svg' )?.getAttribute( 'style' ) ).toContain( 'width:1em' );

        stop();
    } );

    it( 'drops intrinsic dimensions and keeps the 1em sizing last', async () => {
        const root = canvas( iconHtml() );
        const stop = watchInlineIconPlaceholders( root, () => Promise.resolve(
            '<svg width="512" height="512" style="width:9em" viewBox="0 0 1 1"><path d="M0 0"/></svg>',
        ) );

        await waitFor( () => expect( root.querySelector( 'path' ) ).not.toBeNull() );

        const svg = root.querySelector( 'svg' ) as SVGElement;

        expect( svg.hasAttribute( 'width' ) ).toBe( false );
        expect( svg.getAttribute( 'style' ) ).toMatch( /^width:9em;display:inline-block;width:1em/ );

        stop();
    } );

    it( 'cannot turn text inside an attribute value into an attribute', async () => {
        const root = canvas( iconHtml() );
        const stop = watchInlineIconPlaceholders( root, () => Promise.resolve(
            '<svg class="a style=\'x onload=alert(1) y\'"><path d="M0 0"/></svg>',
        ) );

        await waitFor( () => expect( root.querySelector( 'path' ) ).not.toBeNull() );

        expect( root.querySelector( 'svg' )?.hasAttribute( 'onload' ) ).toBe( false );

        stop();
    } );

    it( 'looks an icon up by its set and name', () => {
        const fetchSvg = vi.fn( () => Promise.resolve( GLYPH ) );
        const stop     = watchInlineIconPlaceholders( canvas( iconHtml( 'star' ) ), fetchSvg );

        expect( fetchSvg ).toHaveBeenCalledWith( 'fas', 'star' );

        stop();
    } );

    it( 'leaves an icon with a glyph and a custom SVG alone', () => {
        const fetchSvg = vi.fn( () => Promise.resolve( GLYPH ) );
        const stop     = watchInlineIconPlaceholders(
            canvas( `${ iconHtml( 'star', GLYPH ) }<span class="ap-inline-icon">${ SET_ICON_PLACEHOLDER_SVG }</span>` ),
            fetchSvg,
        );

        expect( fetchSvg ).not.toHaveBeenCalled();

        stop();
    } );

    it( 'fills icons that render after it starts', async () => {
        const root = canvas( '' );
        const stop = watchInlineIconPlaceholders( root, () => Promise.resolve( GLYPH ) );

        root.innerHTML = iconHtml();

        await waitFor( () => expect( root.querySelector( 'path' ) ).not.toBeNull() );

        stop();
    } );

    it( 're-fills an icon rich-text resets to its placeholder', async () => {
        const root = canvas( iconHtml() );
        const stop = watchInlineIconPlaceholders( root, () => Promise.resolve( GLYPH ) );

        await waitFor( () => expect( root.querySelector( 'path' ) ).not.toBeNull() );

        ( root.firstElementChild as Element ).innerHTML = SET_ICON_PLACEHOLDER_SVG;

        await waitFor( () => expect( root.querySelector( 'path' ) ).not.toBeNull() );

        stop();
    } );

    it( 'does not retry an unresolved icon on every mutation', async () => {
        const fetchSvg = vi.fn( () => Promise.resolve( '' ) );
        const root     = canvas( iconHtml() );
        const stop     = watchInlineIconPlaceholders( root, fetchSvg );

        await act( async () => {
            await Promise.resolve();
        } );

        root.appendChild( document.createElement( 'p' ) );

        await act( async () => {
            await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
        } );

        expect( fetchSvg ).toHaveBeenCalledTimes( 1 );
        expect( root.querySelector( 'svg' )?.outerHTML ).toBe( SET_ICON_PLACEHOLDER_SVG );

        stop();
    } );

    it.each( [
        [ 'an SVG with no glyph', SET_ICON_PLACEHOLDER_SVG ],
        [ 'markup that is not an SVG', '<img src="x" />' ],
        [ 'an SVG with a sibling', `${ GLYPH }<img src="x" />` ],
    ] )( 'leaves the placeholder in place for %s, once', async ( _label, response ) => {
        const fetchSvg = vi.fn( () => Promise.resolve( response ) );
        const root     = canvas( iconHtml() );
        const stop     = watchInlineIconPlaceholders( root, fetchSvg );

        await act( async () => {
            await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
        } );

        expect( fetchSvg ).toHaveBeenCalledTimes( 1 );
        expect( root.querySelector( 'img' ) ).toBeNull();
        expect( root.querySelector( 'svg' )?.outerHTML ).toBe( SET_ICON_PLACEHOLDER_SVG );

        stop();
    } );

    it( 'ignores a lookup that settles after it stops', async () => {
        const root = canvas( iconHtml() );
        const stop = watchInlineIconPlaceholders( root, () => Promise.resolve( GLYPH ) );

        stop();

        await act( async () => {
            await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
        } );

        expect( root.querySelector( 'path' ) ).toBeNull();
    } );
} );

describe( 'InlineIconCanvasHydrator', () => {
    it( 'renders only a hidden marker', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal( 'fetch', fetchMock );

        const { container } = render( <InlineIconCanvasHydrator /> );

        expect( ( container.firstElementChild as HTMLElement ).hidden ).toBe( true );
        expect( container.children ).toHaveLength( 1 );
    } );

    it( 'fetches glyphs under the API base the host configured', async () => {
        const fetchMock = vi.fn( async () => new Response( JSON.stringify( { icons: {} } ), { status: 200 } ) );

        vi.stubGlobal( 'fetch', fetchMock );
        setBindingsApiConfig( { apiBase: '/custom/api' } );
        canvas( iconHtml( 'hydrator-base' ) );

        render( <InlineIconCanvasHydrator /> );

        await waitFor( () => expect( fetchMock ).toHaveBeenCalled() );

        expect( String( ( fetchMock.mock.calls[ 0 ] as unknown[] )[ 0 ] ) ).toMatch( /^\/custom\/api\// );
    } );
} );
