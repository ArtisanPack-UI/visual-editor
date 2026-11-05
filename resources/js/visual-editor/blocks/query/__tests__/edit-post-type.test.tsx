/**
 * Query Loop post type changes (#1029): the taxonomy filter survives a
 * change to a post type that supports it and clears otherwise.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';

import { refreshTaxonomies } from '../../../editor/taxonomy-registry';

vi.mock( '@wordpress/block-editor', () => ( {
    BlockContextProvider: ( { children }: { children: ReactNode } ) => <>{ children }</>,
    InnerBlocks: () => null,
    InspectorControls: ( { children }: { children: ReactNode } ) => <>{ children }</>,
    useBlockProps: () => ( {} ),
} ) );

vi.mock( '../post-variants-panel', () => ( { default: () => null } ) );

import QueryEdit from '../edit';

const fetchMock = vi.fn();

function renderEdit( query: Record<string, unknown> ) {
    const setAttributes = vi.fn();

    render(
        <QueryEdit
            attributes={ { queryId: 'block-1', query } }
            setAttributes={ setAttributes }
            clientId="block-1"
        />
    );

    return setAttributes;
}

beforeEach( () => {
    fetchMock.mockReset();
    fetchMock.mockImplementation( async ( url: string ) => new Response(
        JSON.stringify( url.includes( 'query/resolve' )
            ? { data: [], meta: { total: 0 } }
            : { data: [] } ),
        { status: 200 }
    ) );
    vi.stubGlobal( 'fetch', fetchMock );

    const mount = document.createElement( 'div' );
    mount.setAttribute( 'data-ap-visual-editor', '' );
    mount.setAttribute( 'data-taxonomies', JSON.stringify( [
        { slug: 'category', label: 'Category', plural: 'Categories' },
        { slug: 'genre', label: 'Genre', plural: 'Genres' },
    ] ) );
    document.body.appendChild( mount );
    refreshTaxonomies();
} );

afterEach( () => {
    cleanup();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
    refreshTaxonomies();
} );

describe( 'QueryEdit post type changes', () => {
    it( 'clears a taxonomy the new post type does not support', () => {
        const setAttributes = renderEdit( {
            postType: 'post',
            taxQuery: { taxonomy: 'category', terms: [ 1 ], operator: 'IN' },
        } );

        fireEvent.change( screen.getByLabelText( 'Post type' ), { target: { value: 'page' } } );

        expect( setAttributes ).toHaveBeenLastCalledWith( {
            query: { postType: 'page', taxQuery: null },
        } );
    } );

    it( 'keeps a taxonomy the new post type supports', () => {
        const taxQuery = { taxonomy: 'genre', terms: [ 1 ], operator: 'IN' };
        const setAttributes = renderEdit( { postType: 'post', taxQuery } );

        fireEvent.change( screen.getByLabelText( 'Post type' ), { target: { value: 'page' } } );

        expect( setAttributes ).toHaveBeenLastCalledWith( {
            query: { postType: 'page', taxQuery },
        } );
    } );

    it( 'sends the filter with the preview request', async () => {
        renderEdit( {
            postType: 'post',
            taxQuery: { taxonomy: 'genre', terms: [ 1 ], operator: 'IN' },
        } );

        await waitFor( () => {
            const call = fetchMock.mock.calls.find( ( [ url ] ) => String( url ).includes( 'query/resolve' ) );

            expect( JSON.parse( call?.[ 1 ].body ).taxQuery ).toEqual( {
                taxonomy: 'genre',
                terms: [ 1 ],
                operator: 'IN',
            } );
        } );
    } );
} );
