/**
 * Tests for the query-family sibling blocks reading the
 * `artisanpack/queryPreview` block context (#599):
 *
 *   - query-title shows the resolved title when present.
 *   - query-pagination-next shows an active state when more pages exist.
 *   - query-pagination-previous shows the muted state on canvas page 1.
 *   - query-pagination-numbers computes a plausible page run from
 *     total + perPage.
 *   - query-no-results gates the InnerBlocks render on `showInEditor`
 *     (design-time toggle) — but always renders content when the
 *     resolver actually returned zero matches (front-end semantics).
 */

import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock( '@wordpress/block-editor', async () => {
    const { createElement } = await import( 'react' );
    return {
        InnerBlocks: () => createElement( 'div', { 'data-testid': 'inner-blocks' }, 'Inner blocks' ),
        InspectorControls: ( { children }: { children: React.ReactNode } ) =>
            createElement( 'div', { 'data-testid': 'inspector-controls' }, children ),
        useBlockProps: Object.assign(
            ( props?: Record<string, unknown> ) => ( {
                className: typeof props?.className === 'string' ? props.className : 'wp-block',
            } ),
            { save: () => ( {} ) }
        ),
    };
} );

vi.mock( '@wordpress/components', async () => {
    const { createElement } = await import( 'react' );
    return {
        PanelBody: ( { children }: { children: React.ReactNode } ) =>
            createElement( 'div', { 'data-testid': 'panel-body' }, children ),
        ToggleControl: ( {
            label,
            checked,
            onChange,
        }: {
            label: string;
            checked: boolean;
            onChange: ( next: boolean ) => void;
        } ) =>
            createElement(
                'label',
                null,
                label,
                createElement( 'input', {
                    type: 'checkbox',
                    'data-testid': 'toggle-show-in-editor',
                    checked,
                    onChange: ( event: React.ChangeEvent<HTMLInputElement> ) =>
                        onChange( event.target.checked ),
                } )
            ),
    };
} );

vi.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
} ) );

import QueryTitleEdit from '../query-title/edit';
import QueryPaginationNextEdit from '../query-pagination-next/edit';
import QueryPaginationPreviousEdit from '../query-pagination-previous/edit';
import QueryPaginationNumbersEdit from '../query-pagination-numbers/edit';
import QueryNoResultsEdit from '../query-no-results/edit';

import { QUERY_PREVIEW_CONTEXT_KEY } from '../../editor/query-preview-context';
import type { QueryPreviewContextValue } from '../../editor/query-preview-context';

function previewContext( overrides: Partial<QueryPreviewContextValue> = {} ): Record<string, unknown> {
    return {
        [ QUERY_PREVIEW_CONTEXT_KEY ]: {
            posts: [],
            total: 0,
            currentPage: 1,
            queryTitle: '',
            perPage: 0,
            status: 'ready',
            ...overrides,
        },
    };
}

describe( 'QueryTitleEdit', () => {
    it( 'renders the placeholder when no resolved title is available', () => {
        const { getByText } = render(
            <QueryTitleEdit attributes={ { type: 'archive', level: 1 } } />
        );
        expect( getByText( 'Archive title' ) ).not.toBeNull();
    } );

    it( 'prefers the stamped _resolvedQueryTitle attribute over the context value', () => {
        const { getByText, queryByText } = render(
            <QueryTitleEdit
                attributes={ {
                    type: 'archive',
                    level: 1,
                    _resolvedQueryTitle: 'Stamped title',
                } as Record<string, unknown> }
                context={ previewContext( { queryTitle: 'Context title' } ) }
            />
        );
        expect( getByText( 'Stamped title' ) ).not.toBeNull();
        expect( queryByText( 'Context title' ) ).toBeNull();
    } );

    it( 'falls back to the queryPreview context title when the attribute is empty', () => {
        const { getByText } = render(
            <QueryTitleEdit
                attributes={ { type: 'archive', level: 1 } }
                context={ previewContext( { queryTitle: 'From context' } ) }
            />
        );
        expect( getByText( 'From context' ) ).not.toBeNull();
    } );
} );

describe( 'QueryPaginationNextEdit', () => {
    it( 'renders an active state when the total exceeds perPage on canvas page 1', () => {
        const { container } = render(
            <QueryPaginationNextEdit
                attributes={ {} }
                context={ previewContext( { total: 10, perPage: 3 } ) }
            />
        );
        // Active state has the arrow glyph appended.
        expect( container.textContent ).toContain( 'Next Page' );
        expect( container.textContent ).toContain( '→' ); // →
    } );

    it( 'renders a muted placeholder when no more pages exist', () => {
        const { container } = render(
            <QueryPaginationNextEdit
                attributes={ {} }
                context={ previewContext( { total: 2, perPage: 5 } ) }
            />
        );
        expect( container.textContent ).toContain( 'Next Page' );
        expect( container.textContent ).not.toContain( '→' );
    } );

    it( 'renders an active state when _resolvedNextPageUrl is stamped on the saved tree', () => {
        const { container } = render(
            <QueryPaginationNextEdit
                attributes={ { _resolvedNextPageUrl: 'https://example.test/page/2' } }
            />
        );
        expect( container.textContent ).toContain( '→' );
    } );
} );

describe( 'QueryPaginationPreviousEdit', () => {
    it( 'renders a muted placeholder by default (canvas always previews page 1)', () => {
        const { container } = render(
            <QueryPaginationPreviousEdit attributes={ {} } />
        );
        expect( container.textContent ).toContain( 'Previous Page' );
        expect( container.textContent ).not.toContain( '←' );
    } );

    it( 'renders an active state when _resolvedPreviousPageUrl is stamped', () => {
        const { container } = render(
            <QueryPaginationPreviousEdit
                attributes={ { _resolvedPreviousPageUrl: 'https://example.test/' } }
            />
        );
        expect( container.textContent ).toContain( '←' );
    } );
} );

describe( 'QueryPaginationNumbersEdit', () => {
    it( 'falls back to the static text when no preview context is present', () => {
        const { container } = render(
            <QueryPaginationNumbersEdit attributes={ {} } />
        );
        expect( container.textContent ).toContain( '1 2 3' );
    } );

    it( 'computes a plausible page run from total + perPage', () => {
        const { container } = render(
            <QueryPaginationNumbersEdit
                attributes={ {} }
                context={ previewContext( { total: 12, perPage: 3 } ) }
            />
        );
        // ceil(12/3) = 4 pages → "1 2 3 4"
        expect( container.textContent ).toContain( '1' );
        expect( container.textContent ).toContain( '4' );
        // Should NOT contain a page number that exceeds the computed count.
        expect( container.textContent ).not.toContain( '5' );
    } );

    it( 'caps the displayed numbers to a reasonable preview length', () => {
        const { container } = render(
            <QueryPaginationNumbersEdit
                attributes={ {} }
                context={ previewContext( { total: 100, perPage: 3 } ) }
            />
        );
        // ceil(100/3) = 34 pages, but preview caps at 5 numbers.
        expect( container.textContent ).toContain( '1' );
        expect( container.textContent ).toContain( '5' );
        expect( container.textContent ).not.toContain( '6' );
    } );

    it( 'marks page 1 as the current page (canvas always previews page 1)', () => {
        const { container } = render(
            <QueryPaginationNumbersEdit
                attributes={ {} }
                context={ previewContext( { total: 9, perPage: 3 } ) }
            />
        );
        const current = container.querySelector( '[aria-current="page"]' );
        expect( current?.textContent ).toBe( '1' );
    } );

    it( 'prefers a stamped pagination numbers label when present', () => {
        const { container } = render(
            <QueryPaginationNumbersEdit
                attributes={ { _resolvedPaginationNumbersLabel: 'pre-rendered 1 2 3' } }
                context={ previewContext( { total: 12, perPage: 3 } ) }
            />
        );
        expect( container.textContent ).toBe( 'pre-rendered 1 2 3' );
    } );
} );

describe( 'QueryNoResultsEdit', () => {
    it( 'hides the inner template by default (showInEditor=false and resolver returned matches)', () => {
        const { queryByTestId, container } = render(
            <QueryNoResultsEdit
                attributes={ {} }
                setAttributes={ () => {} }
                context={ previewContext( { total: 5, perPage: 5, posts: [
                    { id: 1, title: 'Hit' },
                ] as never } ) }
            />
        );

        expect( queryByTestId( 'inner-blocks' ) ).toBeNull();
        expect( container.textContent ).toContain( 'No Results state (hidden — toggle on to style it)' );
    } );

    it( 'renders the inner template when showInEditor is true (design-time toggle)', () => {
        const { getByTestId } = render(
            <QueryNoResultsEdit
                attributes={ { showInEditor: true } }
                setAttributes={ () => {} }
            />
        );

        expect( getByTestId( 'inner-blocks' ) ).not.toBeNull();
    } );

    it( 'renders the inner template when the resolver returned zero matches regardless of toggle state', () => {
        const { getByTestId } = render(
            <QueryNoResultsEdit
                attributes={ {} }
                setAttributes={ () => {} }
                context={ previewContext( { total: 0, status: 'ready' } ) }
            />
        );

        expect( getByTestId( 'inner-blocks' ) ).not.toBeNull();
    } );

    it( 'exposes the toggle through InspectorControls so users can flip it on', () => {
        const { getByTestId } = render(
            <QueryNoResultsEdit
                attributes={ { showInEditor: false } }
                setAttributes={ () => {} }
            />
        );

        expect( getByTestId( 'toggle-show-in-editor' ) ).not.toBeNull();
    } );
} );
