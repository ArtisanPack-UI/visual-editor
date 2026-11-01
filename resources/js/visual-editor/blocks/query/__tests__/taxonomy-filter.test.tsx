/**
 * Query Loop taxonomy filter (#1029).
 *
 * Covers writing and clearing `query.taxQuery`, the taxonomy list
 * following the post type, and the `query.taxQuery` shapes it reads.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';

import { refreshTaxonomies } from '../../../editor/taxonomy-registry';
import TaxonomyFilter, {
    isTaxonomyAvailable,
    readTaxQuery,
    type QueryTaxQuery,
} from '../taxonomy-filter';

const fetchMock = vi.fn();

function mountTaxonomies(): void {
    const mount = document.createElement( 'div' );
    mount.setAttribute( 'data-ap-visual-editor', '' );
    mount.setAttribute( 'data-taxonomies', JSON.stringify( [
        { slug: 'category', label: 'Category', plural: 'Categories' },
        { slug: 'package', label: 'Package', plural: 'Packages', postTypes: [ 'post' ] },
        { slug: 'genre', label: 'Genre', plural: 'Genres' },
    ] ) );
    document.body.appendChild( mount );
    refreshTaxonomies();
}

function respondWithTerms( terms: unknown[], status = 200 ): void {
    fetchMock.mockResolvedValue(
        new Response( JSON.stringify( { data: terms } ), { status } )
    );
}

function renderFilter( postType: string, initial: unknown = null ) {
    const onChange = vi.fn();

    function Harness() {
        const [ taxQuery, setTaxQuery ] = useState( initial );

        return (
            <TaxonomyFilter
                postType={ postType }
                taxQuery={ taxQuery }
                onChange={ ( next: QueryTaxQuery | null ) => {
                    onChange( next );
                    setTaxQuery( next );
                } }
            />
        );
    }

    render( <Harness /> );

    return onChange;
}

function taxonomySelect(): HTMLSelectElement {
    return screen.getByLabelText( 'Taxonomy' ) as HTMLSelectElement;
}

function optionValues(): string[] {
    return Array.from( taxonomySelect().options ).map( ( option ) => option.value );
}

beforeEach( () => {
    fetchMock.mockReset();
    vi.stubGlobal( 'fetch', fetchMock );
    mountTaxonomies();
} );

afterEach( () => {
    cleanup();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
    refreshTaxonomies();
} );

describe( 'TaxonomyFilter', () => {
    it( 'lists only the taxonomies of the selected post type', () => {
        renderFilter( 'page' );

        expect( optionValues() ).toEqual( [ '', 'genre' ] );
    } );

    it( 'includes host-registered taxonomies for posts', () => {
        renderFilter( 'post' );

        expect( optionValues() ).toEqual( [ '', 'category', 'package', 'genre' ] );
    } );

    it( 'renders nothing when the post type has no taxonomies', () => {
        document.body.innerHTML = '';
        const mount = document.createElement( 'div' );
        mount.setAttribute( 'data-ap-visual-editor', '' );
        mount.setAttribute( 'data-taxonomies', JSON.stringify( [
            { slug: 'category', label: 'Category', plural: 'Categories' },
        ] ) );
        document.body.appendChild( mount );
        refreshTaxonomies();

        renderFilter( 'page' );

        expect( screen.queryByLabelText( 'Taxonomy' ) ).toBeNull();
    } );

    it( 'loads the chosen taxonomy\'s terms and writes the selection', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' }, { id: 4, name: 'Forms' } ] );
        const onChange = renderFilter( 'post' );

        fireEvent.change( taxonomySelect(), { target: { value: 'package' } } );

        fireEvent.click( await screen.findByLabelText( 'Hooks' ) );
        expect( onChange ).toHaveBeenLastCalledWith( {
            taxonomy: 'package',
            terms: [ 3 ],
            operator: 'IN',
        } );

        fireEvent.click( screen.getByLabelText( 'Forms' ) );
        expect( onChange ).toHaveBeenLastCalledWith( {
            taxonomy: 'package',
            terms: [ 3, 4 ],
            operator: 'IN',
        } );

        expect( fetchMock.mock.calls[ 0 ][ 0 ] ).toBe( '/visual-editor/api/taxonomies/package/terms' );
    } );

    it( 'does not write a taxonomy that has no terms picked', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' } ] );
        const onChange = renderFilter( 'post' );

        fireEvent.change( taxonomySelect(), { target: { value: 'package' } } );
        await screen.findByLabelText( 'Hooks' );

        expect( onChange ).not.toHaveBeenCalled();
    } );

    it( 'sets taxQuery to null when the last term is unchecked, and keeps the taxonomy', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' } ] );
        const onChange = renderFilter( 'post', { taxonomy: 'package', terms: [ 3 ], operator: 'IN' } );

        fireEvent.click( await screen.findByLabelText( 'Hooks' ) );

        expect( onChange ).toHaveBeenLastCalledWith( null );
        expect( taxonomySelect().value ).toBe( 'package' );
    } );

    it( 'sets taxQuery to null when the taxonomy is cleared', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' } ] );
        const onChange = renderFilter( 'post', { taxonomy: 'package', terms: [ 3 ], operator: 'IN' } );

        await screen.findByLabelText( 'Hooks' );
        fireEvent.change( taxonomySelect(), { target: { value: '' } } );

        expect( onChange ).toHaveBeenLastCalledWith( null );
        expect( taxonomySelect().value ).toBe( '' );
        expect( screen.queryByLabelText( 'Hooks' ) ).toBeNull();
    } );

    it( 'drops the selected terms when switching taxonomy', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' } ] );
        const onChange = renderFilter( 'post', { taxonomy: 'package', terms: [ 3 ], operator: 'IN' } );

        await screen.findByLabelText( 'Hooks' );
        fireEvent.change( taxonomySelect(), { target: { value: 'genre' } } );

        expect( onChange ).toHaveBeenLastCalledWith( null );
        expect( taxonomySelect().value ).toBe( 'genre' );
    } );

    it( 'keeps a saved taxonomy the post type no longer lists', () => {
        respondWithTerms( [] );
        renderFilter( 'page', { taxonomy: 'package', terms: [ 3 ], operator: 'IN' } );

        expect( optionValues() ).toContain( 'package' );
        expect( taxonomySelect().value ).toBe( 'package' );
    } );

    it( 'lists a selected term that no longer exists so it can be removed', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' } ] );
        const onChange = renderFilter( 'post', { taxonomy: 'package', terms: [ 3, 9 ], operator: 'IN' } );

        fireEvent.click( await screen.findByLabelText( 'Term #9 (unavailable)' ) );

        expect( onChange ).toHaveBeenLastCalledWith( {
            taxonomy: 'package',
            terms: [ 3 ],
            operator: 'IN',
        } );
    } );

    it( 'never shows the previous taxonomy\'s terms while the next ones load', async () => {
        respondWithTerms( [ { id: 3, name: 'Hooks' } ] );
        renderFilter( 'post' );

        fireEvent.change( taxonomySelect(), { target: { value: 'package' } } );
        await screen.findByLabelText( 'Hooks' );

        fetchMock.mockReturnValue( new Promise( () => undefined ) );
        fireEvent.change( taxonomySelect(), { target: { value: 'genre' } } );

        expect( screen.queryByLabelText( 'Hooks' ) ).toBeNull();
        expect( screen.getByText( 'Loading terms…' ) ).toBeTruthy();
    } );

    it( 'tells the author when the taxonomy has no terms', async () => {
        respondWithTerms( [] );
        renderFilter( 'post' );

        fireEvent.change( taxonomySelect(), { target: { value: 'genre' } } );

        expect( await screen.findByText( 'No terms found.' ) ).toBeTruthy();
    } );

    it( 'shows an error when the terms request fails', async () => {
        respondWithTerms( [], 500 );
        renderFilter( 'post' );

        fireEvent.change( taxonomySelect(), { target: { value: 'genre' } } );

        await waitFor( () => expect( screen.getByText( 'Terms could not be loaded.' ) ).toBeTruthy() );
    } );
} );

describe( 'readTaxQuery', () => {
    it( 'reads a filter this control wrote', () => {
        expect( readTaxQuery( { taxonomy: 'genre', terms: [ 1, 2 ], operator: 'IN' } ) ).toEqual( {
            taxonomy: 'genre',
            terms: [ 1, 2 ],
            operator: 'IN',
        } );
    } );

    it( 'reads numeric string term ids', () => {
        expect( readTaxQuery( { taxonomy: 'genre', terms: [ '3' ] } )?.terms ).toEqual( [ 3 ] );
    } );

    it.each( [
        [ 'null', null ],
        [ 'the empty placeholder', '' ],
        [ 'no terms', { taxonomy: 'genre', terms: [] } ],
        [ 'no taxonomy', { taxonomy: '', terms: [ 1 ] } ],
        [ 'the upstream shape', { category: [ 1 ] } ],
        [ 'invalid term ids', { taxonomy: 'genre', terms: [ 0, 'x' ] } ],
    ] )( 'ignores %s', ( _label, value ) => {
        expect( readTaxQuery( value ) ).toBeNull();
    } );
} );

describe( 'isTaxonomyAvailable', () => {
    it( 'follows the post type', () => {
        expect( isTaxonomyAvailable( 'package', 'post' ) ).toBe( true );
        expect( isTaxonomyAvailable( 'package', 'page' ) ).toBe( false );
        expect( isTaxonomyAvailable( 'genre', 'page' ) ).toBe( true );
    } );
} );
