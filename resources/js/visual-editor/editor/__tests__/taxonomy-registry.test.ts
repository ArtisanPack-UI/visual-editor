/**
 * Taxonomy registry (#771).
 *
 * Verifies the `data-taxonomies` mount attribute is parsed into the
 * descriptor list the post-terms variations + Settings picker consume,
 * that both editor mounts are read, and that malformed entries degrade
 * to the built-in defaults.
 */

import { afterEach, describe, it, expect } from 'vitest';

import {
    getTaxonomies,
    refreshTaxonomies,
    type TaxonomyDescriptor,
} from '../taxonomy-registry';

function mount( selector: 'data-ap-visual-editor' | 'data-ap-site-editor', taxonomies: unknown ): void {
    const el = document.createElement( 'div' );
    el.setAttribute( selector, '' );
    if ( taxonomies !== undefined ) {
        el.setAttribute( 'data-taxonomies', JSON.stringify( taxonomies ) );
    }
    document.body.appendChild( el );
}

afterEach( () => {
    document.body.innerHTML = '';
    refreshTaxonomies();
} );

describe( 'taxonomy registry', () => {
    it( 'parses the data-taxonomies attribute from the post-editor mount', () => {
        mount( 'data-ap-visual-editor', [
            { slug: 'category', label: 'Category', plural: 'Categories' },
            { slug: 'genre', label: 'Genre', plural: 'Genres' },
        ] );

        const result = getTaxonomies();

        expect( result ).toEqual<ReadonlyArray<TaxonomyDescriptor>>( [
            { slug: 'category', label: 'Category', plural: 'Categories' },
            { slug: 'genre', label: 'Genre', plural: 'Genres' },
        ] );
    } );

    it( 'reads the site-editor mount when no post-editor mount is present', () => {
        mount( 'data-ap-site-editor', [
            { slug: 'topic', label: 'Topic', plural: 'Topics' },
        ] );

        expect( getTaxonomies() ).toEqual( [
            { slug: 'topic', label: 'Topic', plural: 'Topics' },
        ] );
    } );

    it( 'falls back to the singular label when plural is missing', () => {
        mount( 'data-ap-visual-editor', [ { slug: 'genre', label: 'Genre' } ] );

        expect( getTaxonomies() ).toEqual( [
            { slug: 'genre', label: 'Genre', plural: 'Genre' },
        ] );
    } );

    it( 'drops entries with unsafe slugs or missing labels', () => {
        mount( 'data-ap-visual-editor', [
            { slug: 'bad slug', label: 'Bad' },
            { slug: 'ok', label: '' },
            { slug: 'genre', label: 'Genre', plural: 'Genres' },
        ] );

        expect( getTaxonomies() ).toEqual( [
            { slug: 'genre', label: 'Genre', plural: 'Genres' },
        ] );
    } );

    it( 'falls back to the category/post_tag defaults when the attribute is missing', () => {
        mount( 'data-ap-visual-editor', undefined );

        expect( getTaxonomies() ).toEqual( [
            { slug: 'category', label: 'Category', plural: 'Categories' },
            { slug: 'post_tag', label: 'Tag', plural: 'Tags' },
        ] );
    } );

    it( 'falls back to the defaults when the attribute is not valid JSON', () => {
        const el = document.createElement( 'div' );
        el.setAttribute( 'data-ap-visual-editor', '' );
        el.setAttribute( 'data-taxonomies', '{not json' );
        document.body.appendChild( el );

        expect( getTaxonomies().map( ( t ) => t.slug ) ).toEqual( [
            'category',
            'post_tag',
        ] );
    } );

    it( 'caches the snapshot until refreshTaxonomies is called', () => {
        mount( 'data-ap-visual-editor', [ { slug: 'genre', label: 'Genre' } ] );
        expect( getTaxonomies().map( ( t ) => t.slug ) ).toEqual( [ 'genre' ] );

        document.body.innerHTML = '';
        mount( 'data-ap-visual-editor', [ { slug: 'topic', label: 'Topic' } ] );

        // Still cached from the first read.
        expect( getTaxonomies().map( ( t ) => t.slug ) ).toEqual( [ 'genre' ] );

        refreshTaxonomies();
        expect( getTaxonomies().map( ( t ) => t.slug ) ).toEqual( [ 'topic' ] );
    } );
} );
