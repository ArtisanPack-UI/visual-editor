/**
 * Snackbar notices (#1066).
 */

import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { dispatch, select } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

import { EditorSnackbars } from '../editor-snackbars';

afterEach( () => {
    for ( const { id } of select( noticesStore ).getNotices() ) {
        dispatch( noticesStore ).removeNotice( id );
    }
} );

describe( 'EditorSnackbars', () => {
    it( 'is a labelled region a keyboard user can find (#1066)', () => {
        render( <EditorSnackbars /> );

        const region = screen.getByRole( 'region', { name: 'Editor notifications' } );

        expect( region.getAttribute( 'tabindex' ) ).toBe( '-1' );
    } );

    it( 'shows snackbars, not other notices, with their actions', async () => {
        const onClick = vi.fn();

        const { container } = render( <EditorSnackbars /> );

        await act( async () => {
            dispatch( noticesStore ).createInfoNotice( 'A snackbar', {
                type: 'snackbar',
                explicitDismiss: true,
                actions: [ { label: 'Do it', onClick } ],
            } );
            dispatch( noticesStore ).createInfoNotice( 'A default notice' );
        } );

        const snackbars = Array.from( container.querySelectorAll( '.components-snackbar' ) );

        expect( snackbars ).toHaveLength( 1 );
        expect( snackbars[ 0 ].textContent ).toContain( 'A snackbar' );

        fireEvent.click( screen.getByRole( 'button', { name: 'Do it' } ) );

        expect( onClick ).toHaveBeenCalledOnce();
    } );

    it( 'stacks in the corner rather than positioning a list off screen', async () => {
        const { container } = render( <EditorSnackbars /> );

        await act( async () => {
            dispatch( noticesStore ).createInfoNotice( 'A snackbar', { type: 'snackbar' } );
        } );

        expect( container.querySelector( '.components-snackbar-list' ) ).toBeNull();
        expect( ( container.firstChild as HTMLElement ).style.position ).toBe( 'fixed' );
    } );
} );
