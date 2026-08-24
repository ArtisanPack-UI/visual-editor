/**
 * Inline-icon RichText format tests (#717).
 *
 * The `edit` component is stubbed so `register.ts` can load without
 * pulling `@wordpress/block-editor` into the jsdom graph. Everything
 * else runs against the real `@wordpress/rich-text` engine so the
 * insert / replace / remove / save contract is exercised end to end.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    insert,
    store as richTextStore,
    toHTMLString,
    unregisterFormatType,
} from '@wordpress/rich-text';
import { select } from '@wordpress/data';

vi.mock( '../edit', () => ( {
    InlineIconEdit: (): null => null,
} ) );

/** `getFormatType` (singular) isn't a public export — query the store. */
function getFormatType( name: string ): unknown {
    const selectors = select( richTextStore as never ) as unknown as {
        getFormatType( name: string ): unknown;
    };
    return selectors.getFormatType( name );
}

import { registerInlineIconFormat } from '../register';
import {
    FORMAT_NAME,
    buildCustomSvgObject,
    buildInlineIconStyle,
    buildSetIconObject,
    normalizeInlineIconSvg,
    parseInlineIconStyle,
} from '../settings';
import {
    insertInlineIcon,
    removeActiveInlineIcon,
    replaceActiveInlineIcon,
} from '../value';

function ensureRegistered(): void {
    if ( ! getFormatType( FORMAT_NAME ) ) {
        registerInlineIconFormat();
    }
}

afterEach( () => {
    if ( getFormatType( FORMAT_NAME ) ) {
        unregisterFormatType( FORMAT_NAME );
    }
} );

describe( 'inline-icon pure builders', () => {
    it( 'omits the style when nothing is overridden (inherit-first)', () => {
        expect( buildInlineIconStyle( {} ) ).toBeUndefined();
    } );

    it( 'builds a style from size and colour overrides', () => {
        expect( buildInlineIconStyle( { sizeEm: 1.5, color: '#f00' } ) ).toBe( 'font-size:1.5em;color:#f00' );
    } );

    it( 'round-trips size and colour through parseInlineIconStyle', () => {
        const style = buildInlineIconStyle( { sizeEm: 2, color: 'rebeccapurple' } );
        expect( parseInlineIconStyle( style ) ).toEqual( { sizeEm: 2, color: 'rebeccapurple' } );
    } );

    it( 'marks a set icon decorative by default', () => {
        const object = buildSetIconObject( { set: 'fab', name: 'github' }, '<svg/>' );
        expect( object.type ).toBe( FORMAT_NAME );
        expect( object.attributes ).toMatchObject( {
            iconSet: 'fab',
            iconName: 'github',
            hidden: 'true',
        } );
        expect( object.attributes.role ).toBeUndefined();
    } );

    it( 'promotes a labelled icon to role=img with no aria-hidden', () => {
        const object = buildSetIconObject( { set: 'fab', name: 'github' }, '<svg/>', { label: 'GitHub' } );
        expect( object.attributes ).toMatchObject( { role: 'img', label: 'GitHub' } );
        expect( object.attributes.hidden ).toBeUndefined();
    } );

    it( 'falls back to a non-empty placeholder body when the preview svg is empty', () => {
        // A contentEditable:false object with an empty body is dropped on
        // save, so the reference span must never persist empty.
        const object = buildSetIconObject( { set: 'fab', name: 'github' }, '' );
        expect( object.innerHTML.trim() ).not.toBe( '' );
        expect( object.attributes ).toMatchObject( { iconSet: 'fab', iconName: 'github' } );
    } );

    it( 'inlines sizing/colour onto the svg and strips intrinsic dimensions', () => {
        const out = normalizeInlineIconSvg( '<svg width="512" height="512" viewBox="0 0 512 512"><path d="M0 0"/></svg>' );
        expect( out ).toContain( 'style="display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em"' );
        expect( out ).not.toContain( 'width="512"' );
        expect( out ).not.toContain( 'height="512"' );
        expect( out ).toContain( 'viewBox="0 0 512 512"' );
    } );

    it( 'appends the enforced style after an existing style so 1em wins', () => {
        const out = normalizeInlineIconSvg( '<svg style="width:512px;color:red"><path d="M0 0"/></svg>' );
        expect( out ).toContain( 'style="width:512px;color:red;display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em"' );
        // No duplicate style attribute.
        expect( out.match( /style=/g ) ).toHaveLength( 1 );
    } );

    it( 'merges a single-quoted style attribute without duplicating it', () => {
        const out = normalizeInlineIconSvg( "<svg style='color:red'><path d=\"M0 0\"/></svg>" );
        expect( out ).toContain( 'style="color:red;display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em"' );
        expect( out.match( /style=/g ) ).toHaveLength( 1 );
    } );

    it( 'normalizes a self-closing svg root without corrupting the tag', () => {
        const out = normalizeInlineIconSvg( '<svg viewBox="0 0 1 1"/>' );
        expect( out ).toBe( '<svg viewBox="0 0 1 1" style="display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em"/>' );
    } );

    it( 'returns a non-svg string untouched', () => {
        expect( normalizeInlineIconSvg( 'not an svg' ) ).toBe( 'not an svg' );
    } );

    it( 'builds a custom-svg object with no set reference', () => {
        const object = buildCustomSvgObject( '<svg id="x"/>' );
        expect( object.attributes.iconSet ).toBeUndefined();
        expect( object.attributes.iconName ).toBeUndefined();
        expect( object.innerHTML ).toContain( 'id="x"' );
        expect( object.innerHTML ).toContain( 'width:1em;height:1em;fill:currentColor' );
    } );
} );

describe( 'inline-icon registration', () => {
    it( 'registers the format with the reference-span contract', () => {
        ensureRegistered();
        const type = getFormatType( FORMAT_NAME ) as Record< string, unknown >;

        expect( type ).toBeTruthy();
        expect( type.tagName ).toBe( 'span' );
        expect( type.className ).toBe( 'ap-inline-icon' );
        expect( type.contentEditable ).toBe( false );
        expect( type.attributes ).toMatchObject( {
            iconSet: 'data-icon-set',
            iconName: 'data-icon-name',
        } );
    } );

    it( 'is idempotent — a second call does not throw', () => {
        ensureRegistered();
        expect( () => registerInlineIconFormat() ).not.toThrow();
    } );
} );

describe( 'inline-icon save output', () => {
    beforeEach( ensureRegistered );

    it( 'inserts a registered-set icon as a reference span carrying the preview svg', () => {
        const value = insertInlineIcon(
            insert( create( { html: 'Read more ' } ), '' ),
            buildSetIconObject( { set: 'fab', name: 'github' }, '<svg id="gh"></svg>' ),
        );

        const html = toHTMLString( { value } );

        expect( html ).toContain( 'Read more ' );
        expect( html ).toContain( 'class="ap-inline-icon"' );
        expect( html ).toContain( 'data-icon-set="fab"' );
        expect( html ).toContain( 'data-icon-name="github"' );
        expect( html ).toContain( 'aria-hidden="true"' );
        expect( html ).toContain( 'id="gh"' );
        // Sizing/colour are inlined onto the svg so it renders correctly
        // inside the editor's style-isolated canvas iframe.
        expect( html ).toContain( 'width:1em;height:1em;fill:currentColor' );
    } );

    it( 'inserts a custom svg inline with no set reference', () => {
        const value = insertInlineIcon(
            create( { html: '' } ),
            buildCustomSvgObject( '<svg viewBox="0 0 1 1"></svg>' ),
        );

        const html = toHTMLString( { value } );

        expect( html ).toContain( 'class="ap-inline-icon"' );
        expect( html ).toContain( 'viewBox="0 0 1 1"' );
        expect( html ).toContain( 'width:1em;height:1em;fill:currentColor' );
        expect( html ).not.toContain( 'data-icon-set' );
    } );

    it( 'serializes size and colour overrides onto the span style', () => {
        const value = insertInlineIcon(
            create( { html: '' } ),
            buildSetIconObject( { set: 'fab', name: 'github' }, '<svg></svg>', { sizeEm: 1.5, color: '#0a0' } ),
        );

        const html = toHTMLString( { value } );

        expect( html ).toContain( 'font-size:1.5em' );
        expect( html ).toContain( 'color:#0a0' );
    } );

    it( 'round-trips a saved reference span through create → toHTMLString', () => {
        const saved = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github" aria-hidden="true"><svg id="gh"></svg></span>';

        const value = create( { html: saved } );
        const html  = toHTMLString( { value } );

        expect( html ).toContain( 'data-icon-set="fab"' );
        expect( html ).toContain( 'data-icon-name="github"' );
        expect( html ).toContain( '<svg id="gh"></svg>' );
    } );
} );

describe( 'inline-icon value operations', () => {
    beforeEach( ensureRegistered );

    it( 'replaces the active icon in place, preserving its position', () => {
        const value = insertInlineIcon(
            create( { html: '' } ),
            buildSetIconObject( { set: 'fab', name: 'github' }, '<svg id="gh"></svg>' ),
        );

        // Point the selection at the object (it sits at index 0).
        const atIcon = { ...value, start: 0, end: 1 };
        const next   = replaceActiveInlineIcon(
            atIcon,
            buildSetIconObject( { set: 'fab', name: 'gitlab' }, '<svg id="gl"></svg>' ),
        );

        const html = toHTMLString( { value: next } );
        expect( html ).toContain( 'data-icon-name="gitlab"' );
        expect( html ).not.toContain( 'data-icon-name="github"' );
    } );

    it( 'removes the active icon', () => {
        const value = insertInlineIcon(
            create( { html: '' } ),
            buildSetIconObject( { set: 'fab', name: 'github' }, '<svg></svg>' ),
        );

        const atIcon = { ...value, start: 0, end: 1 };
        const html   = toHTMLString( { value: removeActiveInlineIcon( atIcon ) } );

        expect( html ).not.toContain( 'ap-inline-icon' );
    } );
} );
