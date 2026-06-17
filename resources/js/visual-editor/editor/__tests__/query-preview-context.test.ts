/**
 * Unit tests for the `artisanpack/queryPreview` context helpers (#599).
 *
 * Covers the read-side guards (malformed shapes shouldn't crash a
 * descendant edit), the iteration-count cap math, and the shape of
 * what a well-formed context produces.
 */

import { describe, expect, it } from 'vitest';

import type { QueryPreviewPost } from '../use-query-preview';
import {
    QUERY_PREVIEW_CONTEXT_KEY,
    QUERY_PREVIEW_ITERATION_CAP,
    getQueryPreviewIterationCount,
    readQueryPreviewContext,
} from '../query-preview-context';

function makePost( id: number, title = `Post ${ id }` ): QueryPreviewPost {
    return { id, title };
}

describe( 'readQueryPreviewContext', () => {
    it( 'returns null when the context is missing or malformed', () => {
        expect( readQueryPreviewContext( null ) ).toBeNull();
        expect( readQueryPreviewContext( undefined ) ).toBeNull();
        expect( readQueryPreviewContext( 'string' ) ).toBeNull();
        expect( readQueryPreviewContext( {} ) ).toBeNull();
        expect( readQueryPreviewContext( { [ QUERY_PREVIEW_CONTEXT_KEY ]: 'not-an-object' } ) ).toBeNull();
    } );

    it( 'extracts a well-formed context with defaults applied to missing fields', () => {
        const context = {
            [ QUERY_PREVIEW_CONTEXT_KEY ]: {
                posts: [ makePost( 1 ), makePost( 2 ) ],
                total: 5,
                currentPage: 1,
                queryTitle: 'Latest',
                perPage: 3,
                status: 'ready',
            },
        };

        const preview = readQueryPreviewContext( context );

        expect( preview ).not.toBeNull();
        expect( preview?.posts ).toHaveLength( 2 );
        expect( preview?.total ).toBe( 5 );
        expect( preview?.currentPage ).toBe( 1 );
        expect( preview?.queryTitle ).toBe( 'Latest' );
        expect( preview?.perPage ).toBe( 3 );
        expect( preview?.status ).toBe( 'ready' );
    } );

    it( 'filters posts without a numeric id', () => {
        const context = {
            [ QUERY_PREVIEW_CONTEXT_KEY ]: {
                posts: [ makePost( 1 ), { title: 'No id' }, null, makePost( 2 ) ],
                total: 4,
                currentPage: 1,
                queryTitle: '',
                perPage: 0,
                status: 'ready',
            },
        };

        const preview = readQueryPreviewContext( context );

        expect( preview?.posts ).toHaveLength( 2 );
        expect( preview?.posts.map( ( p ) => p.id ) ).toEqual( [ 1, 2 ] );
    } );

    it( 'clamps malformed numeric values to safe defaults', () => {
        const context = {
            [ QUERY_PREVIEW_CONTEXT_KEY ]: {
                posts: [],
                total: -5,
                currentPage: 0,
                queryTitle: 42,
                perPage: -3,
                status: 'bogus',
            },
        };

        const preview = readQueryPreviewContext( context );

        expect( preview?.total ).toBe( 0 );
        expect( preview?.currentPage ).toBe( 1 );
        expect( preview?.queryTitle ).toBe( '' );
        expect( preview?.perPage ).toBe( 0 );
        expect( preview?.status ).toBe( 'idle' );
    } );
} );

describe( 'getQueryPreviewIterationCount', () => {
    it( 'returns posts.length when perPage is undefined or zero', () => {
        const posts = [ makePost( 1 ), makePost( 2 ), makePost( 3 ) ];
        expect( getQueryPreviewIterationCount( posts, undefined ) ).toBe( 3 );
        expect( getQueryPreviewIterationCount( posts, 0 ) ).toBe( 3 );
    } );

    it( 'caps at perPage when it is smaller than posts.length', () => {
        const posts = [ makePost( 1 ), makePost( 2 ), makePost( 3 ) ];
        expect( getQueryPreviewIterationCount( posts, 2 ) ).toBe( 2 );
    } );

    it( 'caps at posts.length when perPage exceeds it', () => {
        const posts = [ makePost( 1 ), makePost( 2 ) ];
        expect( getQueryPreviewIterationCount( posts, 10 ) ).toBe( 2 );
    } );

    it( `caps at ${ QUERY_PREVIEW_ITERATION_CAP } even when perPage and posts.length are larger`, () => {
        const posts = Array.from( { length: 50 }, ( _value, index ) => makePost( index + 1 ) );
        expect( getQueryPreviewIterationCount( posts, 50 ) ).toBe( QUERY_PREVIEW_ITERATION_CAP );
    } );
} );
