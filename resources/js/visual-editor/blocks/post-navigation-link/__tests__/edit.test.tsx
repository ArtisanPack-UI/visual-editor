/**
 * Tests for the `artisanpack/post-navigation-link` edit component.
 *
 * Covers the InspectorControls wiring added in #532: the Direction
 * ToggleGroupControl reflects `attributes.type`, the showTitle
 * ToggleControl reflects `attributes.showTitle`, and the Arrow
 * SelectControl reflects `attributes.arrow`. Each control routes
 * its onChange through `setAttributes` so the canvas preview
 * (already reactive on `attributes.type` / `attributes.arrow`)
 * follows the toggle without dropping into HTML edit mode.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, fireEvent } from '@testing-library/react';

vi.mock( '@wordpress/i18n', () => ( {
    __: ( text: string ) => text,
} ) );

vi.mock( '@wordpress/block-editor', () => ( {
    InspectorControls: ( { children }: { children?: React.ReactNode } ) => (
        <div data-testid="inspector">{ children }</div>
    ),
    useBlockProps: ( props?: Record<string, unknown> ) => ( {
        ...props,
        'data-testid': 'empty-wrapper',
    } ),
} ) );

vi.mock( '@wordpress/components', () => {
    // `vi.mock` factories are hoisted above the file's imports, so we
    // can't pull React in via the top-level `import React from 'react'`
    // line — `require()` is the supported escape hatch for getting
    // React inside a hoisted factory. Lifting it to mock scope keeps
    // the call once-per-mount instead of once-per-render.
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const React = require( 'react' );

    return {
        PanelBody: ( { children }: { children?: React.ReactNode } ) => (
            <div data-testid="panel">{ children }</div>
        ),
        SelectControl: ( {
            label,
            value,
            options,
            onChange,
        }: {
            label?: string;
            value?: string;
            options?: ReadonlyArray<{ label: string; value: string }>;
            onChange?: ( value: string ) => void;
        } ) => (
            <select
                data-testid="arrow-select"
                aria-label={ label }
                value={ value }
                onChange={ ( event ) => onChange?.( event.target.value ) }
            >
                { ( options ?? [] ).map( ( option ) => (
                    <option key={ option.value } value={ option.value }>
                        { option.label }
                    </option>
                ) ) }
            </select>
        ),
        ToggleControl: ( {
            label,
            checked,
            onChange,
        }: {
            label?: string;
            checked?: boolean;
            onChange?: ( value: boolean ) => void;
        } ) => (
            <input
                type="checkbox"
                data-testid="show-title-toggle"
                aria-label={ label }
                checked={ Boolean( checked ) }
                onChange={ ( event ) => onChange?.( event.target.checked ) }
            />
        ),
        __experimentalToggleGroupControl: ( {
            label,
            value,
            onChange,
            children,
        }: {
            label?: string;
            value?: string;
            onChange?: ( value: string | number ) => void;
            children?: React.ReactNode;
        } ) => {
            // Wire each child option through `onChange` so clicking an
            // option exercises the real `setAttributes` write path. The
            // production `ToggleGroupControl` does the same plumbing through
            // a context; cloneElement is a lighter equivalent for the mock.
            const wired = React.Children.map(
                children,
                ( child: React.ReactNode ) =>
                    React.isValidElement( child )
                        ? React.cloneElement( child, { onChange } )
                        : child,
            );

            return (
                <div data-testid="direction-toggle-group" data-value={ value } aria-label={ label }>
                    { wired }
                </div>
            );
        },
        __experimentalToggleGroupControlOption: ( {
            value,
            label,
            onChange,
        }: {
            value?: string;
            label?: string;
            onChange?: ( value: string | number ) => void;
        } ) => (
            <button
                data-testid={ `direction-${ value }` }
                type="button"
                onClick={ () => {
                    if ( value !== undefined ) {
                        onChange?.( value );
                    }
                } }
            >
                { label }
            </button>
        ),
    };
} );

// Stub the shared placeholder edit factory so the suite focuses on the
// InspectorControls wiring this test owns. The real factory pulls in
// the core-data shim and the live-entity hooks, which are out of scope
// for the toggle assertions below.
vi.mock( '../../_shared/entity-placeholder-edit', () => ( {
    createEntityPlaceholderEdit: () => ( props: { attributes?: Record<string, unknown> } ) => (
        <div
            data-testid="placeholder-preview"
            data-resolved={ String( props.attributes?._resolvedAdjacentTitle ?? '' ) }
        />
    ),
    PREVIEW_CONTEXT_KEY: 'artisanpack/postPreview',
} ) );

( globalThis as { React?: unknown } ).React = require( 'react' );

import PostNavigationLinkEdit from '../edit';

describe( 'PostNavigationLinkEdit InspectorControls', () => {
    it( 'mounts the InspectorControls panel when setAttributes is provided', () => {
        const { getByTestId } = render(
            <PostNavigationLinkEdit attributes={ {} } setAttributes={ vi.fn() } />,
        );

        expect( getByTestId( 'inspector' ) ).toBeTruthy();
        expect( getByTestId( 'panel' ) ).toBeTruthy();
        expect( getByTestId( 'direction-toggle-group' ) ).toBeTruthy();
    } );

    it( 'omits the InspectorControls panel when setAttributes is not provided', () => {
        const { queryByTestId } = render(
            <PostNavigationLinkEdit attributes={ {} } />,
        );

        expect( queryByTestId( 'inspector' ) ).toBeNull();
    } );

    it( 'reflects the current `type` attribute on the Direction toggle group', () => {
        const { getByTestId, rerender } = render(
            <PostNavigationLinkEdit
                attributes={ { type: 'previous' } }
                setAttributes={ vi.fn() }
            />,
        );

        expect(
            getByTestId( 'direction-toggle-group' ).getAttribute( 'data-value' ),
        ).toBe( 'previous' );

        rerender(
            <PostNavigationLinkEdit attributes={ { type: 'next' } } setAttributes={ vi.fn() } />,
        );

        expect(
            getByTestId( 'direction-toggle-group' ).getAttribute( 'data-value' ),
        ).toBe( 'next' );
    } );

    it( 'defaults the Direction toggle to "next" when `type` is unset or unknown', () => {
        const { getByTestId, rerender } = render(
            <PostNavigationLinkEdit attributes={ {} } setAttributes={ vi.fn() } />,
        );

        expect(
            getByTestId( 'direction-toggle-group' ).getAttribute( 'data-value' ),
        ).toBe( 'next' );

        rerender(
            <PostNavigationLinkEdit
                attributes={ { type: 'sideways' } }
                setAttributes={ vi.fn() }
            />,
        );

        expect(
            getByTestId( 'direction-toggle-group' ).getAttribute( 'data-value' ),
        ).toBe( 'next' );
    } );

    it( 'routes the Direction toggle through setAttributes for both options', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <PostNavigationLinkEdit
                attributes={ { type: 'next' } }
                setAttributes={ setAttributes }
            />,
        );

        fireEvent.click( getByTestId( 'direction-previous' ) );
        expect( setAttributes ).toHaveBeenCalledWith( { type: 'previous' } );

        fireEvent.click( getByTestId( 'direction-next' ) );
        expect( setAttributes ).toHaveBeenCalledWith( { type: 'next' } );
    } );

    it( 'routes the showTitle toggle through setAttributes', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <PostNavigationLinkEdit
                attributes={ { showTitle: false } }
                setAttributes={ setAttributes }
            />,
        );

        const toggle = getByTestId( 'show-title-toggle' ) as HTMLInputElement;
        expect( toggle.checked ).toBe( false );

        fireEvent.click( toggle );

        expect( setAttributes ).toHaveBeenCalledWith( { showTitle: true } );
    } );

    it( 'routes the Arrow select through setAttributes', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            <PostNavigationLinkEdit
                attributes={ { arrow: 'none' } }
                setAttributes={ setAttributes }
            />,
        );

        const select = getByTestId( 'arrow-select' ) as HTMLSelectElement;
        expect( select.value ).toBe( 'none' );

        fireEvent.change( select, { target: { value: 'chevron' } } );

        expect( setAttributes ).toHaveBeenCalledWith( { arrow: 'chevron' } );
    } );

    it( 'decorates the placeholder preview with the configured arrow glyph in each direction', () => {
        const { getByTestId, rerender } = render(
            <PostNavigationLinkEdit
                attributes={ {
                    type: 'next',
                    arrow: 'arrow',
                    _resolvedNextTitle: 'Newer release',
                } }
                setAttributes={ vi.fn() }
            />,
        );

        expect(
            getByTestId( 'placeholder-preview' ).getAttribute( 'data-resolved' ),
        ).toBe( 'Newer release →' );

        rerender(
            <PostNavigationLinkEdit
                attributes={ {
                    type: 'previous',
                    arrow: 'chevron',
                    _resolvedPrevTitle: 'Older release',
                } }
                setAttributes={ vi.fn() }
            />,
        );

        expect(
            getByTestId( 'placeholder-preview' ).getAttribute( 'data-resolved' ),
        ).toBe( '« Older release' );
    } );

    it( 'renders an empty block wrapper (no placeholder text) when no adjacent post and no label resolve', () => {
        const { getByTestId, queryByTestId } = render(
            <PostNavigationLinkEdit
                attributes={ { type: 'next', arrow: 'arrow' } }
                setAttributes={ vi.fn() }
            />,
        );

        expect( queryByTestId( 'placeholder-preview' ) ).toBeNull();
        const wrapper = getByTestId( 'empty-wrapper' );
        expect( wrapper.tagName ).toBe( 'DIV' );
        expect( wrapper.textContent ).toBe( '' );
    } );

    it( 'still renders the InspectorControls panel when the empty-wrapper branch is taken', () => {
        const { getByTestId } = render(
            <PostNavigationLinkEdit attributes={ {} } setAttributes={ vi.fn() } />,
        );

        expect( getByTestId( 'inspector' ) ).toBeTruthy();
        expect( getByTestId( 'empty-wrapper' ) ).toBeTruthy();
    } );

    it( 'falls back to the custom `label` attribute when no adjacent post is resolved', () => {
        const { getByTestId } = render(
            <PostNavigationLinkEdit
                attributes={ { type: 'previous', arrow: 'arrow', label: 'Older articles' } }
                setAttributes={ vi.fn() }
            />,
        );

        expect(
            getByTestId( 'placeholder-preview' ).getAttribute( 'data-resolved' ),
        ).toBe( '← Older articles' );
    } );

    it( 'prefers query-preview adjacent title over the custom label when both are present', () => {
        const { getByTestId } = render(
            <PostNavigationLinkEdit
                attributes={ { type: 'next', arrow: 'none', label: 'Older articles' } }
                context={ {
                    'artisanpack/postPreview': {
                        adjacent: { next: { title: 'Adjacent title', url: '/x' } },
                    },
                } }
                setAttributes={ vi.fn() }
            />,
        );

        expect(
            getByTestId( 'placeholder-preview' ).getAttribute( 'data-resolved' ),
        ).toBe( 'Adjacent title' );
    } );
} );
