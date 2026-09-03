/**
 * post-terms block variations (#771).
 *
 * Asserts one inserter variation is generated per registered taxonomy,
 * that each pre-sets the `term` attribute to the taxonomy slug, and that
 * `category` is the default so a bare insertion binds to categories.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock( '../../../editor/taxonomy-registry', () => ( {
    getTaxonomies: () => [
        { slug: 'category', label: 'Category', plural: 'Categories' },
        { slug: 'post_tag', label: 'Tag', plural: 'Tags' },
        { slug: 'genre', label: 'Genre', plural: 'Genres' },
    ],
} ) );

import variations from '../variations';

interface Variation {
    name: string;
    title: string;
    attributes: { term?: string };
    isActive?: unknown;
    isDefault?: boolean;
    keywords?: readonly string[];
    scope?: readonly string[];
}

const list = variations as unknown as Variation[];

describe( 'post-terms variations', () => {
    it( 'generates one variation per registered taxonomy', () => {
        expect( list.map( ( v ) => v.name ) ).toEqual( [
            'term-category',
            'term-post_tag',
            'term-genre',
        ] );
    } );

    it( 'pre-sets the term attribute to the taxonomy slug', () => {
        expect( list.map( ( v ) => v.attributes.term ) ).toEqual( [
            'category',
            'post_tag',
            'genre',
        ] );
    } );

    it( 'titles each variation with the taxonomy label', () => {
        const genre = list.find( ( v ) => v.name === 'term-genre' );
        expect( genre?.title ).toBe( 'Genre' );
        expect( genre?.keywords ).toContain( 'Genres' );
    } );

    it( 'marks only the category variation as default', () => {
        const defaults = list.filter( ( v ) => v.isDefault === true );
        expect( defaults ).toHaveLength( 1 );
        expect( defaults[ 0 ]?.name ).toBe( 'term-category' );
    } );

    it( 'matches on the term attribute so reopened blocks highlight the right variation', () => {
        for ( const variation of list ) {
            expect( variation.isActive ).toEqual( [ 'term' ] );
            expect( variation.scope ).toContain( 'inserter' );
        }
    } );
} );
