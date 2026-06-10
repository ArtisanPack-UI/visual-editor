/**
 * Static metadata + module-shape tests for `artisanpack/icon`.
 *
 * We mock `@wordpress/block-editor` because importing `../index` pulls
 * in edit/save which lean on `useBlockProps` — and the upstream module
 * has a transitive `diff` import that vitest can't resolve outside the
 * Vite build. The mock is enough to let the modules load and expose
 * their default exports.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock( '@wordpress/block-editor', () => ( {
    useBlockProps: Object.assign(
        ( props?: Record< string, unknown > ) => ( { ...props } ),
        { save: ( props?: Record< string, unknown > ) => ( { ...props } ) }
    ),
} ) );

vi.mock( '@wordpress/i18n', () => ( {
    __: ( s: string ) => s,
} ) );

import metadata from '../block.json';
import iconModule from '../index';

describe( 'artisanpack/icon block.json', () => {
    it( 'declares the artisanpack namespace and design category', () => {
        expect( metadata.name ).toBe( 'artisanpack/icon' );
        expect( metadata.category ).toBe( 'design' );
    } );

    it( 'declares every attribute required by Phase 1', () => {
        const required = [
            'iconRef',
            'customSvg',
            'size',
            'sizeUnit',
            'color',
            'backgroundColor',
            'rotation',
            'flipH',
            'flipV',
            'link',
            'linkTarget',
            'linkRel',
            'titleAttr',
            'ariaLabel',
            'isDecorative',
        ];
        for ( const attr of required ) {
            expect( metadata.attributes ).toHaveProperty( attr );
        }
    } );

    it( 'restricts sizeUnit to px, em, rem', () => {
        expect( metadata.attributes.sizeUnit.enum ).toEqual( [ 'px', 'em', 'rem' ] );
    } );

    it( 'restricts rotation to multiples of 90', () => {
        expect( metadata.attributes.rotation.enum ).toEqual( [ 0, 90, 180, 270 ] );
    } );
} );

describe( 'artisanpack/icon module', () => {
    it( 'exports metadata, edit, save, and icon', () => {
        expect( iconModule.metadata ).toBe( metadata );
        expect( typeof iconModule.edit ).toBe( 'function' );
        expect( typeof iconModule.save ).toBe( 'function' );
        expect( typeof iconModule.icon ).toBe( 'function' );
    } );
} );
