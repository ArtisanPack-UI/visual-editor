/**
 * Registered-set icon SVG fetch (#717, T2; shared cache #1004).
 *
 * `fetchIconSvg` is a convenience preview fetch: the server render-time
 * hydrator re-resolves the `{ set, name }` reference regardless, so every
 * failure path is expected to fall back to an empty string rather than
 * throw into the caller (which awaits it without a try/catch). Lookups
 * are memoized per session so repeated icons share one request.
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

    it( 'requests distinct icons, sets and api bases separately', async () => {
        const fetchImpl = vi.fn( async () => okJson( { svg: '<svg/>' } ) );
        const impl      = fetchImpl as unknown as typeof fetch;

        await Promise.all( [
            fetchIconSvg( 'fas', 'cube', impl ),
            fetchIconSvg( 'fas', 'star', impl ),
            fetchIconSvg( 'far', 'cube', impl ),
            fetchIconSvg( 'fas', 'cube', impl, '/custom/api' ),
        ] );

        expect( fetchImpl ).toHaveBeenCalledTimes( 4 );
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
