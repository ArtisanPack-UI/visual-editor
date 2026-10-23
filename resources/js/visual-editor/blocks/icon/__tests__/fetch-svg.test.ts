/**
 * Registered-set icon SVG fetch (#717, T2; shared cache #1004).
 *
 * `fetchIconSvg` is a convenience preview fetch: the server render-time
 * hydrator re-resolves the `{ set, name }` reference regardless, so every
 * failure path is expected to fall back to an empty string rather than
 * throw into the caller (which awaits it without a try/catch). Lookups
 * are memoized per session so repeated icons share one request, and
 * distinct icons requested in the same tick share one batched request.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import { clearIconSvgCache, fetchIconSvg } from '../fetch-svg';

function okJson( body: unknown ): Response {
    return {
        ok: true,
        status: 200,
        json: async () => body,
    } as unknown as Response;
}

describe( 'fetchIconSvg', () => {
    beforeEach( () => {
        clearIconSvgCache();
    } );

    it( 'returns the svg markup from a successful response', async () => {
        const fetchImpl = vi.fn( async () => okJson( { svg: '<svg>set</svg>' } ) );

        await expect( fetchIconSvg( 'fab', 'github', fetchImpl as unknown as typeof fetch ) ).resolves.toBe(
            '<svg>set</svg>'
        );

        // The reference is passed through as query params to the icons endpoint.
        const url = String( ( fetchImpl.mock.calls[ 0 ] as unknown[] )[ 0 ] );
        expect( url ).toContain( '/visual-editor/api/icons/svg?' );
        expect( url ).toContain( 'set=fab' );
        expect( url ).toContain( 'name=github' );
    } );

    it( 'honours a custom apiBase', async () => {
        const fetchImpl = vi.fn( async () => okJson( { svg: '<svg/>' } ) );

        await fetchIconSvg( 'fab', 'x', fetchImpl as unknown as typeof fetch, '/custom/api' );

        expect( String( ( fetchImpl.mock.calls[ 0 ] as unknown[] )[ 0 ] ) ).toContain(
            '/custom/api/icons/svg?'
        );
    } );

    it( 'falls back to an empty string on a non-OK response', async () => {
        const fetchImpl = vi.fn(
            async () => ( { ok: false, status: 404, json: async () => ({}) } ) as unknown as Response
        );

        await expect(
            fetchIconSvg( 'fab', 'missing', fetchImpl as unknown as typeof fetch )
        ).resolves.toBe( '' );
    } );

    it( 'falls back to an empty string when the fetch rejects', async () => {
        const fetchImpl = vi.fn( async () => {
            throw new Error( 'network down' );
        } );

        await expect(
            fetchIconSvg( 'fab', 'x', fetchImpl as unknown as typeof fetch )
        ).resolves.toBe( '' );
    } );

    it( 'falls back to an empty string when the body is not JSON', async () => {
        const fetchImpl = vi.fn(
            async () =>
                ( {
                    ok: true,
                    status: 200,
                    json: async () => {
                        throw new Error( 'Unexpected end of JSON input' );
                    },
                } ) as unknown as Response
        );

        await expect(
            fetchIconSvg( 'fab', 'x', fetchImpl as unknown as typeof fetch )
        ).resolves.toBe( '' );
    } );

    it( 'falls back to an empty string when the payload has no svg field', async () => {
        const fetchImpl = vi.fn( async () => okJson( { notSvg: true } ) );

        await expect(
            fetchIconSvg( 'fab', 'x', fetchImpl as unknown as typeof fetch )
        ).resolves.toBe( '' );
    } );

    it( 'shares one in-flight request across concurrent lookups of the same icon', async () => {
        let resolveResponse: ( response: Response ) => void = () => {};
        const fetchImpl = vi.fn(
            () =>
                new Promise< Response >( ( resolve ) => {
                    resolveResponse = resolve;
                } )
        );
        const impl = fetchImpl as unknown as typeof fetch;

        const lookups = Array.from( { length: 7 }, () => fetchIconSvg( 'fas', 'cube', impl ) );
        await vi.waitFor( () => expect( fetchImpl ).toHaveBeenCalled() );
        resolveResponse( okJson( { svg: '<svg>cube</svg>' } ) );

        await expect( Promise.all( lookups ) ).resolves.toEqual( Array( 7 ).fill( '<svg>cube</svg>' ) );
        expect( fetchImpl ).toHaveBeenCalledTimes( 1 );
    } );

    it( 'reuses a settled result for later lookups of the same icon', async () => {
        const fetchImpl = vi.fn( async () => okJson( { svg: '<svg>star</svg>' } ) );
        const impl      = fetchImpl as unknown as typeof fetch;

        await fetchIconSvg( 'fas', 'star', impl );
        await expect( fetchIconSvg( 'fas', 'star', impl ) ).resolves.toBe( '<svg>star</svg>' );

        expect( fetchImpl ).toHaveBeenCalledTimes( 1 );
    } );

    it( 'batches distinct icons requested in the same tick into one request per api base', async () => {
        const fetchImpl = vi.fn( async ( input: RequestInfo | URL ) =>
            String( input ).startsWith( '/custom/api' )
                ? okJson( { svg: '<svg>custom</svg>' } )
                : okJson( {
                    'far:cube': '<svg>far-cube</svg>',
                    'fas:cube': '<svg>fas-cube</svg>',
                    'fas:star': '<svg>fas-star</svg>',
                } )
        );
        const impl = fetchImpl as unknown as typeof fetch;

        await expect(
            Promise.all( [
                fetchIconSvg( 'fas', 'star', impl ),
                fetchIconSvg( 'fas', 'cube', impl ),
                fetchIconSvg( 'far', 'cube', impl ),
                fetchIconSvg( 'fas', 'cube', impl ),
                fetchIconSvg( 'fas', 'cube', impl, '/custom/api' ),
            ] )
        ).resolves.toEqual( [
            '<svg>fas-star</svg>',
            '<svg>fas-cube</svg>',
            '<svg>far-cube</svg>',
            '<svg>fas-cube</svg>',
            '<svg>custom</svg>',
        ] );

        expect( fetchImpl ).toHaveBeenCalledTimes( 2 );

        // Keys are sorted so the same icons always produce the same URL.
        const batchUrl = new URL( String( ( fetchImpl.mock.calls[ 0 ] as unknown[] )[ 0 ] ), 'http://x' );
        expect( batchUrl.pathname ).toBe( '/visual-editor/api/icons/svg' );
        expect( batchUrl.searchParams.get( 'icons' ) ).toBe( 'far:cube,fas:cube,fas:star' );

        // A lone miss for an api base keeps the single-icon form.
        const singleUrl = String( ( fetchImpl.mock.calls[ 1 ] as unknown[] )[ 0 ] );
        expect( singleUrl ).toContain( '/custom/api/icons/svg?' );
        expect( singleUrl ).toContain( 'set=fas' );
    } );

    it( 'leaves cached icons out of a later batch', async () => {
        const fetchImpl = vi
            .fn()
            .mockResolvedValueOnce( okJson( { svg: '<svg>cube</svg>' } ) )
            .mockResolvedValueOnce( okJson( { 'fas:star': '<svg>star</svg>', 'fas:heart': '<svg>heart</svg>' } ) );
        const impl = fetchImpl as unknown as typeof fetch;

        await fetchIconSvg( 'fas', 'cube', impl );
        await Promise.all( [
            fetchIconSvg( 'fas', 'cube', impl ),
            fetchIconSvg( 'fas', 'star', impl ),
            fetchIconSvg( 'fas', 'heart', impl ),
        ] );

        expect( fetchImpl ).toHaveBeenCalledTimes( 2 );
        const batchUrl = new URL( String( fetchImpl.mock.calls[ 1 ][ 0 ] ), 'http://x' );
        expect( batchUrl.searchParams.get( 'icons' ) ).toBe( 'fas:heart,fas:star' );
    } );

    it( 'resolves an unknown icon in a batch to an empty string without failing the others', async () => {
        const fetchImpl = vi
            .fn()
            .mockResolvedValueOnce( okJson( { 'fas:cube': '<svg>cube</svg>', 'fas:nope': null } ) )
            .mockResolvedValueOnce( okJson( { svg: '<svg>nope</svg>' } ) );
        const impl = fetchImpl as unknown as typeof fetch;

        await expect(
            Promise.all( [ fetchIconSvg( 'fas', 'cube', impl ), fetchIconSvg( 'fas', 'nope', impl ) ] )
        ).resolves.toEqual( [ '<svg>cube</svg>', '' ] );

        // The empty result is evicted, so a later lookup retries it alone.
        await expect( fetchIconSvg( 'fas', 'nope', impl ) ).resolves.toBe( '<svg>nope</svg>' );
        expect( fetchImpl ).toHaveBeenCalledTimes( 2 );
    } );

    it( 'resolves every icon in a failed batch to an empty string', async () => {
        const fetchImpl = vi
            .fn()
            .mockRejectedValueOnce( new Error( 'network down' ) )
            .mockResolvedValueOnce( { ok: false, status: 500, json: async () => ({}) } as unknown as Response )
            .mockResolvedValueOnce( okJson( [ '<svg/>' ] ) );
        const impl = fetchImpl as unknown as typeof fetch;

        for ( let attempt = 0; attempt < 3; attempt++ ) {
            await expect(
                Promise.all( [ fetchIconSvg( 'fas', 'cube', impl ), fetchIconSvg( 'fas', 'star', impl ) ] )
            ).resolves.toEqual( [ '', '' ] );
        }

        expect( fetchImpl ).toHaveBeenCalledTimes( 3 );
    } );

    it( 'splits a batch larger than the server cap into chunks', async () => {
        const fetchImpl = vi.fn( async ( input: RequestInfo | URL ) => {
            const keys = new URL( String( input ), 'http://x' ).searchParams.get( 'icons' )?.split( ',' ) ?? [];
            return okJson( Object.fromEntries( keys.map( ( key ) => [ key, `<svg>${ key }</svg>` ] ) ) );
        } );
        const impl = fetchImpl as unknown as typeof fetch;

        const names   = Array.from( { length: 150 }, ( _, index ) => `icon-${ index }` );
        const results = await Promise.all( names.map( ( name ) => fetchIconSvg( 'fas', name, impl ) ) );

        expect( results ).toEqual( names.map( ( name ) => `<svg>fas:${ name }</svg>` ) );
        expect( fetchImpl ).toHaveBeenCalledTimes( 2 );
        const sizes = fetchImpl.mock.calls.map(
            ( call ) => new URL( String( call[ 0 ] ), 'http://x' ).searchParams.get( 'icons' )?.split( ',' ).length
        );
        expect( sizes ).toEqual( [ 100, 50 ] );
    } );

    it( 'requests icons asked for in different ticks separately', async () => {
        const fetchImpl = vi.fn( async () => okJson( { svg: '<svg/>' } ) );
        const impl      = fetchImpl as unknown as typeof fetch;

        await fetchIconSvg( 'fas', 'cube', impl );
        await fetchIconSvg( 'fas', 'star', impl );

        expect( fetchImpl ).toHaveBeenCalledTimes( 2 );
    } );

    it( 'does not cache a failed lookup, so a later lookup retries', async () => {
        const fetchImpl = vi
            .fn()
            .mockRejectedValueOnce( new Error( 'network down' ) )
            .mockResolvedValueOnce( okJson( { svg: '<svg>retry</svg>' } ) );
        const impl = fetchImpl as unknown as typeof fetch;

        await expect( fetchIconSvg( 'fas', 'cube', impl ) ).resolves.toBe( '' );
        await expect( fetchIconSvg( 'fas', 'cube', impl ) ).resolves.toBe( '<svg>retry</svg>' );

        expect( fetchImpl ).toHaveBeenCalledTimes( 2 );
    } );
} );
