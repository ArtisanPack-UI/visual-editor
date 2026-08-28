/**
 * Inline-icon set SVG fetch (#717, T2).
 *
 * `fetchIconSvg` is a convenience preview fetch: the server render-time
 * hydrator re-resolves the `{ set, name }` reference regardless, so every
 * failure path is expected to fall back to an empty string rather than
 * throw into the caller (which awaits it without a try/catch).
 */

import { describe, expect, it, vi } from 'vitest';

import { fetchIconSvg } from '../fetch-svg';

function okJson( body: unknown ): Response {
    return {
        ok: true,
        status: 200,
        json: async () => body,
    } as unknown as Response;
}

describe( 'fetchIconSvg', () => {
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
} );
