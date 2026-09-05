/**
 * post-terms edit component — Settings sidebar (#771).
 *
 * Verifies the InspectorControls panel renders a taxonomy picker
 * populated from the runtime registry plus separator/prefix/suffix
 * controls, that each control writes the matching attribute, and that
 * the panel is suppressed when `setAttributes` is absent (read-only
 * preview contexts).
 */

import { describe, it, expect, vi, beforeAll } from 'vitest';
import { render, fireEvent } from '@testing-library/react';

vi.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
    _x: ( text: string ) => text,
    sprintf: ( fmt: string, ...args: unknown[] ) =>
        fmt.replace( /%s/g, () => String( args.shift() ) ),
} ) );

vi.mock( '@wordpress/block-editor', () => ( {
    InspectorControls: ( { children }: { children: React.ReactNode } ) => (
        <div data-testid="inspector">{ children }</div>
    ),
} ) );

vi.mock( '@wordpress/components', () => ( {
    PanelBody: ( { children }: { children: React.ReactNode } ) => (
        <div data-testid="panel">{ children }</div>
    ),
    SelectControl: ( {
        label,
        value,
        options,
        onChange,
    }: {
        label: string;
        value: string;
        options: { label: string; value: string }[];
        onChange: ( value: string ) => void;
    } ) => (
        <select
            aria-label={ label }
            value={ value }
            onChange={ ( e ) => onChange( e.target.value ) }
        >
            { options.map( ( o ) => (
                <option key={ o.value } value={ o.value }>
                    { o.label }
                </option>
            ) ) }
        </select>
    ),
    TextControl: ( {
        label,
        value,
        onChange,
    }: {
        label: string;
        value: string;
        onChange: ( value: string ) => void;
    } ) => (
        <input
            aria-label={ label }
            value={ value }
            onChange={ ( e ) => onChange( e.target.value ) }
        />
    ),
} ) );

vi.mock( '../../../editor/taxonomy-registry', () => ( {
    getTaxonomies: () => [
        { slug: 'category', label: 'Category', plural: 'Categories' },
        { slug: 'genre', label: 'Genre', plural: 'Genres' },
    ],
} ) );

vi.mock( '../../_shared/entity-placeholder-edit', () => ( {
    createEntityPlaceholderEdit: () => () => (
        <div data-testid="preview" />
    ),
    PREVIEW_CONTEXT_KEY: 'artisanpack/postPreview',
} ) );

beforeAll( async () => {
    ( globalThis as { React?: unknown } ).React = await import( 'react' );
} );

// Imported after the mocks are registered.
// eslint-disable-next-line import/first
import PostTermsEdit from '../edit';

describe( 'PostTermsEdit inspector', () => {
    it( 'renders a taxonomy picker with an option per registered taxonomy', () => {
        const { getByLabelText } = render(
            <PostTermsEdit attributes={ { term: 'category' } } setAttributes={ vi.fn() } />
        );

        const select = getByLabelText( 'Taxonomy' ) as HTMLSelectElement;
        const values = Array.from( select.options ).map( ( o ) => o.value );

        expect( values ).toContain( 'category' );
        expect( values ).toContain( 'genre' );
        // Leading blank "Select a taxonomy…" option.
        expect( values ).toContain( '' );
        expect( select.value ).toBe( 'category' );
    } );

    it( 'writes the term attribute when the taxonomy changes', () => {
        const setAttributes = vi.fn();
        const { getByLabelText } = render(
            <PostTermsEdit attributes={ { term: 'category' } } setAttributes={ setAttributes } />
        );

        fireEvent.change( getByLabelText( 'Taxonomy' ), {
            target: { value: 'genre' },
        } );

        expect( setAttributes ).toHaveBeenCalledWith( { term: 'genre' } );
    } );

    it( 'writes separator, prefix, and suffix attributes', () => {
        const setAttributes = vi.fn();
        const { getByLabelText } = render(
            <PostTermsEdit attributes={ { term: 'category' } } setAttributes={ setAttributes } />
        );

        fireEvent.change( getByLabelText( 'Separator' ), { target: { value: ' / ' } } );
        fireEvent.change( getByLabelText( 'Prefix' ), { target: { value: 'In: ' } } );
        fireEvent.change( getByLabelText( 'Suffix' ), { target: { value: '.' } } );

        expect( setAttributes ).toHaveBeenCalledWith( { separator: ' / ' } );
        expect( setAttributes ).toHaveBeenCalledWith( { prefix: 'In: ' } );
        expect( setAttributes ).toHaveBeenCalledWith( { suffix: '.' } );
    } );

    it( 'appends an unregistered stored term so the value is never dropped', () => {
        const { getByLabelText } = render(
            <PostTermsEdit attributes={ { term: 'custom_tax' } } setAttributes={ vi.fn() } />
        );

        const select = getByLabelText( 'Taxonomy' ) as HTMLSelectElement;
        expect( Array.from( select.options ).map( ( o ) => o.value ) ).toContain(
            'custom_tax'
        );
        expect( select.value ).toBe( 'custom_tax' );
    } );

    it( 'suppresses the inspector when setAttributes is absent', () => {
        const { queryByTestId } = render(
            <PostTermsEdit attributes={ { term: 'category' } } />
        );

        expect( queryByTestId( 'inspector' ) ).toBeNull();
        expect( queryByTestId( 'preview' ) ).not.toBeNull();
    } );
} );
