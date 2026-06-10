/**
 * Tests for the Phase 4 IconPicker — search debouncing, set-chip
 * filtering, recent tray, and keyboard navigation.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';

vi.mock( '@wordpress/i18n', () => ( {
    __: ( s: string ) => s,
    sprintf: ( fmt: string, ...args: unknown[] ) =>
        fmt.replace( /%(\d+)\$[ds]/g, ( _, idx ) => String( args[ Number( idx ) - 1 ] ) ),
} ) );

// `@wordpress/components` Modal portals to document.body and brings a
// number of dependencies the jsdom env doesn't love. Stub it down to
// the primitives the picker actually relies on.
vi.mock( '@wordpress/components', () => ( {
    Modal: ( {
        children,
        title,
    }: {
        children: React.ReactNode;
        title: string;
        onRequestClose?: () => void;
    } ) => (
        <div role="dialog" aria-label={ title }>
            { children }
        </div>
    ),
    Button: ( {
        children,
        onClick,
        disabled,
        title,
        variant,
    }: {
        children: React.ReactNode;
        onClick?: () => void;
        disabled?: boolean;
        title?: string;
        variant?: string;
        size?: string;
    } ) => (
        <button type="button" onClick={ onClick } disabled={ disabled } title={ title } data-variant={ variant }>
            { children }
        </button>
    ),
    Spinner: () => <span role="status">loading</span>,
    TextControl: ( {
        value,
        onChange,
        label,
        placeholder,
    }: {
        value: string;
        onChange: ( v: string ) => void;
        label: string;
        placeholder?: string;
    } ) => (
        <label>
            { label }
            <input
                aria-label={ label }
                value={ value }
                placeholder={ placeholder }
                onChange={ ( event ) => onChange( event.target.value ) }
            />
        </label>
    ),
} ) );

import IconPicker, { RECENT_STORAGE_KEY, SEARCH_DEBOUNCE_MS } from '../icon-picker';

interface FakeStorage {
    readonly store: Record< string, string >;
    getItem( key: string ): string | null;
    setItem( key: string, value: string ): void;
}

function makeStorage( initial: Record< string, string > = {} ): FakeStorage {
    const store = { ...initial };
    return {
        store,
        getItem: ( key ) => ( key in store ? store[ key ] : null ),
        setItem: ( key, value ) => {
            store[ key ] = value;
        },
    };
}

function makeFetcher() {
    const calls: string[] = [];
    const fetcher = vi.fn( async ( url: string ) => {
        calls.push( url );
        if ( url.includes( '/icons/sets' ) ) {
            return {
                data: [
                    { prefix: 'fas', label: 'Solid' },
                    { prefix: 'fab', label: 'Brands' },
                ],
            };
        }
        const params = new URL( url, 'http://x' ).searchParams;
        const q = params.get( 'q' ) ?? '';
        const set = params.get( 'set' );
        const ALL = [
            { name: 'home', set: 'fas', label: 'Home' },
            { name: 'user', set: 'fas', label: 'User' },
            { name: 'github', set: 'fab', label: 'GitHub' },
        ];
        let rows = ALL;
        if ( set ) {
            rows = rows.filter( ( i ) => i.set === set );
        }
        if ( q ) {
            rows = rows.filter( ( i ) => i.name.includes( q.toLowerCase() ) );
        }
        return {
            total: rows.length,
            page: 1,
            per_page: 30,
            data: rows,
        };
    } );
    return { fetcher, calls };
}

beforeEach( () => {
    vi.useFakeTimers();
} );

afterEach( () => {
    vi.useRealTimers();
    vi.clearAllMocks();
} );

describe( 'IconPicker', () => {
    it( 'debounces search input before firing a request', async () => {
        const onSelect = vi.fn();
        const onClose = vi.fn();
        const { fetcher } = makeFetcher();
        const storage = makeStorage();

        render(
            <IconPicker
                onSelect={ onSelect }
                onClose={ onClose }
                fetcher={ fetcher }
                storage={ storage }
            />
        );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        const initialSearchCalls = fetcher.mock.calls.filter( ( [ url ] ) =>
            String( url ).includes( '/icons/search' )
        ).length;

        const input = screen.getByLabelText( 'Search' ) as HTMLInputElement;
        fireEvent.change( input, { target: { value: 'h' } } );
        fireEvent.change( input, { target: { value: 'ho' } } );
        fireEvent.change( input, { target: { value: 'home' } } );

        // No new search yet — still inside the debounce window.
        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS - 20 );
        } );

        const midSearchCalls = fetcher.mock.calls.filter( ( [ url ] ) =>
            String( url ).includes( '/icons/search' )
        ).length;
        expect( midSearchCalls ).toBe( initialSearchCalls );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        const afterSearchCalls = fetcher.mock.calls.filter( ( [ url ] ) =>
            String( url ).includes( '/icons/search' )
        );
        expect( afterSearchCalls.length ).toBeGreaterThan( initialSearchCalls );
        expect( String( afterSearchCalls.at( -1 )?.[ 0 ] ) ).toContain( 'q=home' );
    } );

    it( 'filters results when a set chip is clicked', async () => {
        const { fetcher } = makeFetcher();
        const storage = makeStorage();

        render(
            <IconPicker
                onSelect={ vi.fn() }
                onClose={ vi.fn() }
                fetcher={ fetcher }
                storage={ storage }
            />
        );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        // Pump microtasks left after timer advance.
        await act( async () => {
            await Promise.resolve();
            await Promise.resolve();
        } );
        expect( screen.getByText( 'Brands' ) ).toBeInTheDocument();

        fireEvent.click( screen.getByText( 'Brands' ) );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        const last = fetcher.mock.calls.at( -1 )?.[ 0 ];
        expect( String( last ) ).toContain( 'set=fab' );
    } );

    it( 'persists selections to the recent tray', async () => {
        const onSelect = vi.fn();
        const onClose = vi.fn();
        const { fetcher } = makeFetcher();
        const storage = makeStorage();

        render(
            <IconPicker
                onSelect={ onSelect }
                onClose={ onClose }
                fetcher={ fetcher }
                storage={ storage }
            />
        );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        await act( async () => {
            await Promise.resolve();
            await Promise.resolve();
        } );
        expect( screen.getByText( 'Home' ) ).toBeInTheDocument();

        fireEvent.click( screen.getByText( 'Home' ) );

        expect( onSelect ).toHaveBeenCalledWith( { set: 'fas', name: 'home' } );
        expect( onClose ).toHaveBeenCalled();
        expect( storage.store[ RECENT_STORAGE_KEY ] ).toBeDefined();
        const persisted = JSON.parse( storage.store[ RECENT_STORAGE_KEY ] ) as Array< {
            set: string;
            name: string;
        } >;
        expect( persisted[ 0 ] ).toEqual( { set: 'fas', name: 'home' } );
    } );

    it( 'hydrates the recent tray from storage on open', async () => {
        const { fetcher } = makeFetcher();
        const storage = makeStorage( {
            [ RECENT_STORAGE_KEY ]: JSON.stringify( [ { set: 'fab', name: 'github' } ] ),
        } );

        render(
            <IconPicker
                onSelect={ vi.fn() }
                onClose={ vi.fn() }
                fetcher={ fetcher }
                storage={ storage }
            />
        );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        // Recent label + the recent-tray entry both render the icon
        // name; assert the section header is visible.
        expect( screen.getByText( 'Recent' ) ).toBeInTheDocument();
    } );

    it( 'selects the focused icon when Enter is pressed in the grid', async () => {
        const onSelect = vi.fn();
        const { fetcher } = makeFetcher();
        const storage = makeStorage();

        render(
            <IconPicker
                onSelect={ onSelect }
                onClose={ vi.fn() }
                fetcher={ fetcher }
                storage={ storage }
            />
        );

        await act( async () => {
            await vi.advanceTimersByTimeAsync( SEARCH_DEBOUNCE_MS + 10 );
        } );

        await act( async () => {
            await Promise.resolve();
            await Promise.resolve();
        } );
        expect( screen.getByRole( 'grid' ) ).toBeInTheDocument();

        const grid = screen.getByRole( 'grid' );
        fireEvent.keyDown( grid, { key: 'ArrowRight' } );
        fireEvent.keyDown( grid, { key: 'Enter' } );

        // Focus started at 0, ArrowRight → 1 (user), Enter selects it.
        expect( onSelect ).toHaveBeenCalledWith( { set: 'fas', name: 'user' } );
    } );
} );
