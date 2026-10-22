/**
 * categories edit component — Settings sidebar (#960).
 *
 * Verifies the taxonomy picker is populated from the runtime registry
 * (keeping an unregistered stored slug), that the count-style picker
 * only shows with post counts on, and that the "All" toggle reveals the
 * label field, each control writing its attribute.
 */

import { describe, it, expect, vi, beforeAll } from 'vitest';
import { render, fireEvent } from '@testing-library/react';

vi.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
} ) );

vi.mock( '@wordpress/block-editor', () => ( {
    InspectorControls: ( { children }: { children: React.ReactNode } ) => (
        <div data-testid="inspector">{ children }</div>
    ),
    useBlockProps: () => ( {} ),
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
    ToggleControl: ( {
        label,
        checked,
        onChange,
    }: {
        label: string;
        checked: boolean;
        onChange: ( value: boolean ) => void;
    } ) => (
        <input
            type="checkbox"
            aria-label={ label }
            checked={ checked }
            onChange={ ( e ) => onChange( e.target.checked ) }
        />
    ),
} ) );

vi.mock( '../../../editor/server-side-render', () => ( {
    ServerSideRender: () => <div data-testid="ssr" />,
} ) );

vi.mock( '../../../editor/taxonomy-registry', () => ( {
    getTaxonomies: () => [
        { slug: 'category', label: 'Category', plural: 'Categories' },
        { slug: 'post_tag', label: 'Tag', plural: 'Tags' },
        { slug: 'package-category', label: 'Package category', plural: 'Package categories' },
    ],
} ) );

beforeAll( async () => {
    ( globalThis as { React?: unknown } ).React = await import( 'react' );
} );

// Imported after the mocks are registered.
// eslint-disable-next-line import/first
import CategoriesEdit from '../edit';

describe( 'CategoriesEdit inspector', () => {
    it( 'defaults the taxonomy picker to category and lists every registered taxonomy', () => {
        const { getByLabelText } = render(
            <CategoriesEdit attributes={ {} } setAttributes={ vi.fn() } />
        );

        const select = getByLabelText( 'Taxonomy' ) as HTMLSelectElement;

        expect( select.value ).toBe( 'category' );
        expect( Array.from( select.options ).map( ( o ) => o.value ) ).toEqual( [
            'category',
            'post_tag',
            'package-category',
        ] );
    } );

    it( 'writes the taxonomy attribute and keeps an unregistered stored slug', () => {
        const setAttributes = vi.fn();
        const { getByLabelText } = render(
            <CategoriesEdit attributes={ { taxonomy: 'genre' } } setAttributes={ setAttributes } />
        );

        const select = getByLabelText( 'Taxonomy' ) as HTMLSelectElement;

        expect( select.value ).toBe( 'genre' );

        fireEvent.change( select, { target: { value: 'post_tag' } } );

        expect( setAttributes ).toHaveBeenCalledWith( { taxonomy: 'post_tag' } );
    } );

    it( 'shows the count style picker only with post counts on', () => {
        const setAttributes = vi.fn();
        const { queryByLabelText, rerender, getByLabelText } = render(
            <CategoriesEdit attributes={ {} } setAttributes={ setAttributes } />
        );

        expect( queryByLabelText( 'Count style' ) ).toBeNull();

        rerender(
            <CategoriesEdit attributes={ { showPostCounts: true } } setAttributes={ setAttributes } />
        );

        const select = getByLabelText( 'Count style' ) as HTMLSelectElement;

        expect( select.value ).toBe( 'inline' );

        fireEvent.change( select, { target: { value: 'badge' } } );

        expect( setAttributes ).toHaveBeenCalledWith( { countStyle: 'badge' } );
    } );

    it( 'toggles the All item and reveals its label field', () => {
        const setAttributes = vi.fn();
        const { queryByLabelText, rerender, getByLabelText } = render(
            <CategoriesEdit attributes={ {} } setAttributes={ setAttributes } />
        );

        expect( queryByLabelText( '"All" label' ) ).toBeNull();

        fireEvent.click( getByLabelText( 'Show "All" item' ) );

        expect( setAttributes ).toHaveBeenCalledWith( { showAll: true } );

        rerender(
            <CategoriesEdit attributes={ { showAll: true } } setAttributes={ setAttributes } />
        );

        fireEvent.change( getByLabelText( '"All" label' ), { target: { value: 'Everything' } } );

        expect( setAttributes ).toHaveBeenCalledWith( { allLabel: 'Everything' } );
    } );
} );
