/**
 * Query Loop post-type picker options (#847).
 *
 * Covers the default list, the server-stamped `data-query-post-types`
 * mount attribute (both editor mounts), the JS
 * `ap.visualEditor.query.postTypes` filter, malformed-input fallbacks,
 * and preservation of a saved `postType` that isn't in the list.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
import { addFilter, removeAllFilters } from '@wordpress/hooks';

import {
    QUERY_POST_TYPES_FILTER,
    getQueryPostTypeOptions,
    refreshQueryPostTypeOptions,
} from '../post-type-options';

const DEFAULTS = [
    { label: 'Posts', value: 'post' },
    { label: 'Pages', value: 'page' },
];

function mount( selector: 'data-ap-visual-editor' | 'data-ap-site-editor', options: unknown ): void {
    const el = document.createElement( 'div' );
    el.setAttribute( selector, '' );
    el.setAttribute( 'data-query-post-types', typeof options === 'string' ? options : JSON.stringify( options ) );
    document.body.appendChild( el );
}

afterEach( () => {
    document.body.innerHTML = '';
    removeAllFilters( QUERY_POST_TYPES_FILTER );
    refreshQueryPostTypeOptions();
    vi.restoreAllMocks();
} );

describe( 'getQueryPostTypeOptions', () => {
    it( 'defaults to Posts and Pages when no mount attribute or filter is present', () => {
        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( DEFAULTS );
    } );

    it( 'uses the server-provided list from the post-editor mount', () => {
        mount( 'data-ap-visual-editor', [
            { label: 'Posts', value: 'post' },
            { label: 'Case Studies', value: 'case-study' },
        ] );

        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( [
            { label: 'Posts', value: 'post' },
            { label: 'Case Studies', value: 'case-study' },
        ] );
    } );

    it( 'reads the site-editor mount when the post editor is absent', () => {
        mount( 'data-ap-site-editor', [ { label: 'Events', value: 'event' } ] );

        expect( getQueryPostTypeOptions( 'event' ) ).toEqual( [ { label: 'Events', value: 'event' } ] );
    } );

    it( 'falls back to the defaults when the mount attribute is malformed', () => {
        mount( 'data-ap-visual-editor', '{not json' );

        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( DEFAULTS );
    } );

    it( 'drops invalid server entries and duplicate values', () => {
        mount( 'data-ap-visual-editor', [
            { label: ' Products ', value: ' product ' },
            { label: 'Duplicate', value: 'product' },
            { label: '', value: 'empty-label' },
            { label: 'Bad', value: '../etc' },
            { label: 'Too long', value: 'a'.repeat( 65 ) },
            { label: 'No value' },
            null,
        ] );

        expect( getQueryPostTypeOptions( 'product' ) ).toEqual( [ { label: 'Products', value: 'product' } ] );
    } );

    it( 'lets the JS filter extend the options', () => {
        addFilter( QUERY_POST_TYPES_FILTER, 'test/extend', ( options: Array<{ label: string; value: string }> ) => [
            ...options,
            { label: 'Case Studies', value: 'case-study' },
        ] );

        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( [
            ...DEFAULTS,
            { label: 'Case Studies', value: 'case-study' },
        ] );
    } );

    it( 'runs the JS filter on top of the server-provided list', () => {
        mount( 'data-ap-visual-editor', [ { label: 'Events', value: 'event' } ] );
        addFilter( QUERY_POST_TYPES_FILTER, 'test/extend', ( options: Array<{ label: string; value: string }> ) => [
            ...options,
            { label: 'Venues', value: 'venue' },
        ] );

        expect( getQueryPostTypeOptions( 'event' ) ).toEqual( [
            { label: 'Events', value: 'event' },
            { label: 'Venues', value: 'venue' },
        ] );
    } );

    it( 'does not let a filter mutate the cached server list', () => {
        mount( 'data-ap-visual-editor', [ { label: 'Events', value: 'event' } ] );
        addFilter( QUERY_POST_TYPES_FILTER, 'test/mutate', ( options: Array<{ label: string; value: string }> ) => {
            options[ 0 ].label = 'Mutated';
            return options;
        } );

        getQueryPostTypeOptions( 'event' );
        removeAllFilters( QUERY_POST_TYPES_FILTER );

        expect( getQueryPostTypeOptions( 'event' ) ).toEqual( [ { label: 'Events', value: 'event' } ] );
    } );

    it( 'falls back to the base list when the filter returns nothing usable', () => {
        addFilter( QUERY_POST_TYPES_FILTER, 'test/garbage', () => 'not-an-array' );
        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( DEFAULTS );

        removeAllFilters( QUERY_POST_TYPES_FILTER );
        addFilter( QUERY_POST_TYPES_FILTER, 'test/empty', () => [] );
        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( DEFAULTS );
    } );

    it( 'logs and falls back to the base list when a filter callback throws', () => {
        const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => {} );
        addFilter( QUERY_POST_TYPES_FILTER, 'test/throws', () => {
            throw new Error( 'boom' );
        } );

        expect( getQueryPostTypeOptions( 'post' ) ).toEqual( DEFAULTS );
        expect( consoleError ).toHaveBeenCalledOnce();
    } );

    it( 'preserves a saved postType that is not in the list', () => {
        expect( getQueryPostTypeOptions( 'case-study' ) ).toEqual( [
            ...DEFAULTS,
            { label: 'case-study (unavailable)', value: 'case-study' },
        ] );
    } );

    it( 'does not duplicate a saved postType that the filter provides', () => {
        addFilter( QUERY_POST_TYPES_FILTER, 'test/extend', ( options: Array<{ label: string; value: string }> ) => [
            ...options,
            { label: 'Case Studies', value: 'case-study' },
        ] );

        const options = getQueryPostTypeOptions( 'case-study' );

        expect( options.filter( ( option ) => option.value === 'case-study' ) ).toEqual( [
            { label: 'Case Studies', value: 'case-study' },
        ] );
    } );
} );
