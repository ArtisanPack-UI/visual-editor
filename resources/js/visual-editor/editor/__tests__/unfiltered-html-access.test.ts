/**
 * Custom HTML in the inserter (#1062).
 */

import { afterEach, describe, expect, it } from 'vitest';

import { canUseUnfilteredHtml, setUnfilteredHtmlAccess, withCustomHtmlInserterAccess } from '../unfiltered-html-access';

afterEach( () => setUnfilteredHtmlAccess( false ) );

describe( 'withCustomHtmlInserterAccess', () => {
    it( 'denies by default', () => {
        expect( canUseUnfilteredHtml() ).toBe( false );
    } );

    it( 'hides Custom HTML without the ability, keeping its other supports', () => {
        expect( withCustomHtmlInserterAccess( { supports: { html: false } }, 'core/html' ).supports ).toEqual( { html: false, inserter: false } );
    } );

    it( 'leaves Custom HTML alone with the ability', () => {
        setUnfilteredHtmlAccess( true );

        const settings = { supports: { html: false } };

        expect( withCustomHtmlInserterAccess( settings, 'core/html' ) ).toBe( settings );
    } );

    it( 'ignores other blocks', () => {
        const settings = { supports: {} };

        expect( withCustomHtmlInserterAccess( settings, 'artisanpack/paragraph' ) ).toBe( settings );
    } );
} );
