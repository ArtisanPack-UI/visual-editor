/**
 * Search form action inspector control (#961).
 *
 * The control maps the stored `action` attribute onto a "Submit to"
 * select (Site search / This page / Custom path) plus a path input for
 * the custom case. `@wordpress/components` is stubbed with native
 * elements so the test drives the mapping, not the component library.
 */

import { fireEvent, render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock( '@wordpress/components', () => ( {
    SelectControl: ( { label, value, options, onChange }: {
        label: string;
        value: string;
        options: { value: string; label: string }[];
        onChange: ( value: string ) => void;
    } ) => (
        <select aria-label={ label } value={ value } onChange={ ( event ) => onChange( event.target.value ) }>
            { options.map( ( option ) => (
                <option key={ option.value } value={ option.value }>{ option.label }</option>
            ) ) }
        </select>
    ),
    TextControl: ( { label, value, help, onChange }: {
        label: string;
        value: string;
        help: string;
        onChange: ( value: string ) => void;
    } ) => (
        <>
            <label>
                { label }
                <input value={ value } onChange={ ( event ) => onChange( event.target.value ) } />
            </label>
            <span data-testid="path-help">{ help }</span>
        </>
    ),
} ) );

vi.mock( '@wordpress/i18n', () => ( {
    __: ( value: string ) => value,
} ) );

import SearchFormActionControl, {
    isSameSitePath,
    searchActionMode,
} from '../search-form-action-control';

describe( 'searchActionMode', () => {
    it( 'maps stored values onto the select options', () => {
        expect( searchActionMode( undefined ) ).toBe( 'site' );
        expect( searchActionMode( '' ) ).toBe( 'site' );
        expect( searchActionMode( 'current' ) ).toBe( 'current' );
        expect( searchActionMode( '/blog' ) ).toBe( 'custom' );
    } );
} );

describe( 'isSameSitePath', () => {
    it( 'accepts same-site paths and rejects everything else', () => {
        expect( isSameSitePath( '/blog' ) ).toBe( true );
        expect( isSameSitePath( 'https://evil.test' ) ).toBe( false );
        expect( isSameSitePath( '//evil.test' ) ).toBe( false );
        expect( isSameSitePath( '/\\evil.test' ) ).toBe( false );
        expect( isSameSitePath( 'blog' ) ).toBe( false );
    } );
} );

describe( 'SearchFormActionControl', () => {
    it( 'writes the stored value for each option', () => {
        const onChange = vi.fn();
        const { getByLabelText } = render(
            <SearchFormActionControl action="" onChange={ onChange } />
        );
        const select = getByLabelText( 'Submit to' );

        fireEvent.change( select, { target: { value: 'current' } } );
        fireEvent.change( select, { target: { value: 'custom' } } );

        expect( onChange ).toHaveBeenNthCalledWith( 1, 'current' );
        expect( onChange ).toHaveBeenNthCalledWith( 2, '/' );
    } );

    it( 'shows the path input only for a custom path', () => {
        const { queryByLabelText, rerender } = render(
            <SearchFormActionControl action="current" onChange={ vi.fn() } />
        );

        expect( queryByLabelText( 'Path' ) ).toBeNull();

        rerender( <SearchFormActionControl action="/blog" onChange={ vi.fn() } /> );

        expect( ( queryByLabelText( 'Path' ) as HTMLInputElement ).value ).toBe( '/blog' );
    } );

    it( 'warns about a path the renderers will ignore', () => {
        const { getByTestId } = render(
            <SearchFormActionControl action="https://evil.test" onChange={ vi.fn() } />
        );

        expect( getByTestId( 'path-help' ).textContent ).toContain( 'Only paths on this site' );
    } );

    it( 'goes back to site search when Site search is picked', () => {
        const onChange = vi.fn();
        const { getByLabelText } = render(
            <SearchFormActionControl action="/blog" onChange={ onChange } />
        );

        fireEvent.change( getByLabelText( 'Submit to' ), { target: { value: 'site' } } );

        expect( onChange ).toHaveBeenCalledWith( '' );
    } );
} );
