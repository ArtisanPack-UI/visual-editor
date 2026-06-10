/**
 * Icon picker UI — Phase 4 (#555).
 *
 * Modal picker that hits `/visual-editor/api/icons/search` for a
 * debounced text search and `/visual-editor/api/icons/sets` for the
 * set-family chips. Selection persists a 12-item "recent" tray to
 * `localStorage` so the author lands on a useful first screen on every
 * subsequent open.
 *
 * The component is intentionally self-contained — it imports nothing
 * from the rest of the editor bundle besides `@wordpress/components`
 * primitives, so the same picker can later be reused by sidebar
 * variants or other artisanpack/* blocks that need icon selection.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { CSSProperties, KeyboardEvent, ReactElement } from 'react';
import { Modal, Button, Spinner, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import type { IconRef } from './types';

export const RECENT_STORAGE_KEY = 'artisanpack-visual-editor:icon-picker:recent';
export const RECENT_MAX = 12;
export const SEARCH_DEBOUNCE_MS = 250;

interface IconSet {
    readonly prefix: string;
    readonly label: string;
}

interface SearchResultIcon {
    readonly name: string;
    readonly set: string;
    readonly label: string;
    readonly svg?: string;
}

interface SearchEnvelope {
    readonly total: number;
    readonly page: number;
    readonly per_page: number;
    readonly data: readonly SearchResultIcon[];
}

export interface IconPickerProps {
    readonly onSelect: ( ref: IconRef ) => void;
    readonly onClose: () => void;
    /** Override for tests / SSR-less environments. */
    readonly apiBase?: string;
    /** Override the fetcher for tests. Must return a parsed JSON object. */
    readonly fetcher?: ( url: string ) => Promise< unknown >;
    /** Storage adapter — defaults to `window.localStorage`. */
    readonly storage?: Pick< Storage, 'getItem' | 'setItem' >;
}

function readMetaCsrfToken(): string | null {
    if ( typeof document === 'undefined' ) {
        return null;
    }
    const meta = document.querySelector< HTMLMetaElement >( 'meta[name="csrf-token"]' );
    return meta ? meta.content : null;
}

function readXsrfCookie(): string | null {
    if ( typeof document === 'undefined' ) {
        return null;
    }
    const match = document.cookie.match( /(?:^|;\s*)XSRF-TOKEN=([^;]*)/ );
    return match ? decodeURIComponent( match[ 1 ] ) : null;
}

function buildHeaders(): Record< string, string > {
    const headers: Record< string, string > = { Accept: 'application/json' };
    const csrf = readMetaCsrfToken();
    const xsrf = readXsrfCookie();
    if ( csrf ) {
        headers[ 'X-CSRF-TOKEN' ] = csrf;
    }
    if ( xsrf ) {
        headers[ 'X-XSRF-TOKEN' ] = xsrf;
    }
    return headers;
}

async function defaultFetcher( url: string ): Promise< unknown > {
    const response = await fetch( url, {
        headers: buildHeaders(),
        credentials: 'include',
    } );
    if ( ! response.ok ) {
        throw new Error( `HTTP ${ response.status }` );
    }
    return response.json();
}

function getStorage( override?: Pick< Storage, 'getItem' | 'setItem' > ): Pick< Storage, 'getItem' | 'setItem' > | null {
    if ( override ) {
        return override;
    }
    if ( typeof window === 'undefined' ) {
        return null;
    }
    try {
        return window.localStorage;
    } catch {
        // Private mode / locked-down iframes throw on `localStorage`
        // access. The recent tray is a nice-to-have, so swallow.
        return null;
    }
}

function readRecent( storage: Pick< Storage, 'getItem' | 'setItem' > | null ): IconRef[] {
    if ( ! storage ) {
        return [];
    }
    try {
        const raw = storage.getItem( RECENT_STORAGE_KEY );
        if ( ! raw ) {
            return [];
        }
        const parsed: unknown = JSON.parse( raw );
        if ( ! Array.isArray( parsed ) ) {
            return [];
        }
        return parsed
            .filter(
                ( item ): item is IconRef =>
                    !! item &&
                    'object' === typeof item &&
                    'string' === typeof ( item as IconRef ).set &&
                    'string' === typeof ( item as IconRef ).name
            )
            .slice( 0, RECENT_MAX );
    } catch {
        return [];
    }
}

function writeRecent(
    storage: Pick< Storage, 'getItem' | 'setItem' > | null,
    next: readonly IconRef[]
): void {
    if ( ! storage ) {
        return;
    }
    try {
        storage.setItem( RECENT_STORAGE_KEY, JSON.stringify( next.slice( 0, RECENT_MAX ) ) );
    } catch {
        // Quota / locked-down iframe — silently drop. Recent tray is
        // best-effort.
    }
}

function pushRecent( current: readonly IconRef[], next: IconRef ): IconRef[] {
    const without = current.filter(
        ( item ) => ! ( item.set === next.set && item.name === next.name )
    );
    return [ next, ...without ].slice( 0, RECENT_MAX );
}

const GRID_STYLE: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(64px, 1fr))',
    gap: '8px',
    margin: '12px 0',
    minHeight: '240px',
};

const GRID_BUTTON_STYLE: CSSProperties = {
    aspectRatio: '1 / 1',
    borderWidth: '1px',
    borderStyle: 'solid',
    borderColor: 'transparent',
    borderRadius: '6px',
    background: 'transparent',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '8px',
    fontSize: '11px',
    textAlign: 'center',
};

const GRID_BUTTON_FOCUSED_STYLE: CSSProperties = {
    ...GRID_BUTTON_STYLE,
    borderColor: 'var(--wp-admin-theme-color, #2271b1)',
    background: 'rgba(34, 113, 177, 0.08)',
};

const CHIP_ROW_STYLE: CSSProperties = {
    display: 'flex',
    gap: '6px',
    flexWrap: 'wrap',
    margin: '8px 0',
};

const RECENT_ROW_STYLE: CSSProperties = {
    ...CHIP_ROW_STYLE,
    marginBottom: '12px',
};

export default function IconPicker( props: IconPickerProps ): ReactElement {
    const { onSelect, onClose } = props;
    const apiBase = props.apiBase ?? '/visual-editor/api';
    const fetcher = props.fetcher ?? defaultFetcher;
    const storage = useMemo(
        () => getStorage( props.storage ),
        [ props.storage ]
    );

    const [ query, setQuery ] = useState( '' );
    const [ debouncedQuery, setDebouncedQuery ] = useState( '' );
    const [ activeSet, setActiveSet ] = useState< string | null >( null );
    const [ sets, setSets ] = useState< IconSet[] >( [] );
    const [ results, setResults ] = useState< SearchResultIcon[] >( [] );
    const [ total, setTotal ] = useState( 0 );
    const [ page, setPage ] = useState( 1 );
    const [ loading, setLoading ] = useState( false );
    const [ error, setError ] = useState< string | null >( null );
    const [ focusIndex, setFocusIndex ] = useState( 0 );
    const [ recent, setRecent ] = useState< IconRef[] >( () => readRecent( storage ) );

    const perPage = 30;

    // Debounce the typed query so we don't fire a fetch on every
    // keystroke. 250ms feels live without flooding the endpoint.
    useEffect( () => {
        const handle = setTimeout( () => {
            setDebouncedQuery( query );
            setPage( 1 );
        }, SEARCH_DEBOUNCE_MS );
        return () => clearTimeout( handle );
    }, [ query ] );

    // Load registered icon sets once.
    useEffect( () => {
        let cancelled = false;
        ( async () => {
            try {
                const json = ( await fetcher( `${ apiBase }/icons/sets` ) ) as {
                    data: IconSet[];
                };
                if ( ! cancelled ) {
                    setSets( Array.isArray( json.data ) ? json.data : [] );
                }
            } catch {
                if ( ! cancelled ) {
                    setSets( [] );
                }
            }
        } )();
        return () => {
            cancelled = true;
        };
    }, [ apiBase, fetcher ] );

    // Run the actual search whenever the debounced query, set, or page
    // changes.
    useEffect( () => {
        let cancelled = false;
        setLoading( true );
        setError( null );

        const params = new URLSearchParams();
        if ( debouncedQuery ) {
            params.set( 'q', debouncedQuery );
        }
        if ( activeSet ) {
            params.set( 'set', activeSet );
        }
        params.set( 'page', String( page ) );
        params.set( 'per_page', String( perPage ) );

        ( async () => {
            try {
                const json = ( await fetcher( `${ apiBase }/icons/search?${ params.toString() }` ) ) as SearchEnvelope;
                if ( cancelled ) {
                    return;
                }
                setResults( Array.isArray( json.data ) ? [ ...json.data ] : [] );
                setTotal( typeof json.total === 'number' ? json.total : 0 );
                setFocusIndex( 0 );
            } catch ( err ) {
                if ( cancelled ) {
                    return;
                }
                setError(
                    err instanceof Error
                        ? err.message
                        : __( 'Failed to load icons.', 'artisanpack-visual-editor' )
                );
                setResults( [] );
                setTotal( 0 );
            } finally {
                if ( ! cancelled ) {
                    setLoading( false );
                }
            }
        } )();

        return () => {
            cancelled = true;
        };
    }, [ apiBase, fetcher, debouncedQuery, activeSet, page ] );

    const commitSelection = useCallback(
        ( ref: IconRef ) => {
            const nextRecent = pushRecent( recent, ref );
            setRecent( nextRecent );
            writeRecent( storage, nextRecent );
            onSelect( ref );
            onClose();
        },
        [ onSelect, onClose, recent, storage ]
    );

    const totalPages = Math.max( 1, Math.ceil( total / perPage ) );

    const handleGridKeyDown = useCallback(
        ( event: KeyboardEvent< HTMLDivElement > ) => {
            if ( results.length === 0 ) {
                return;
            }
            // Approximate the visible row width so arrow up/down moves
            // by a row. With a min cell of 64px and modal width of
            // ~600px, six columns is a reasonable assumption — and
            // overshooting is fine because clamp(0, length-1) catches.
            const columns = 6;
            if ( event.key === 'ArrowRight' ) {
                event.preventDefault();
                setFocusIndex( ( i ) => Math.min( results.length - 1, i + 1 ) );
            } else if ( event.key === 'ArrowLeft' ) {
                event.preventDefault();
                setFocusIndex( ( i ) => Math.max( 0, i - 1 ) );
            } else if ( event.key === 'ArrowDown' ) {
                event.preventDefault();
                setFocusIndex( ( i ) => Math.min( results.length - 1, i + columns ) );
            } else if ( event.key === 'ArrowUp' ) {
                event.preventDefault();
                setFocusIndex( ( i ) => Math.max( 0, i - columns ) );
            } else if ( event.key === 'Enter' ) {
                event.preventDefault();
                const target = results[ focusIndex ];
                if ( target ) {
                    commitSelection( { set: target.set, name: target.name } );
                }
            }
        },
        [ results, focusIndex, commitSelection ]
    );

    return (
        <Modal
            title={ __( 'Choose an icon', 'artisanpack-visual-editor' ) }
            onRequestClose={ onClose }
            className="wp-block-artisanpack-icon__picker"
            size="medium"
        >
            <TextControl
                label={ __( 'Search', 'artisanpack-visual-editor' ) }
                value={ query }
                onChange={ ( next ) => setQuery( next ) }
                placeholder={ __( 'home, arrow, github…', 'artisanpack-visual-editor' ) }
                autoComplete="off"
                __next40pxDefaultSize
                __nextHasNoMarginBottom
            />

            { sets.length > 0 && (
                <div className="wp-block-artisanpack-icon__picker-sets" style={ CHIP_ROW_STYLE } role="group" aria-label={ __( 'Filter by icon set', 'artisanpack-visual-editor' ) }>
                    <Button
                        variant={ activeSet === null ? 'primary' : 'secondary' }
                        size="small"
                        onClick={ () => {
                            setActiveSet( null );
                            setPage( 1 );
                        } }
                    >
                        { __( 'All', 'artisanpack-visual-editor' ) }
                    </Button>
                    { sets.map( ( set ) => (
                        <Button
                            key={ set.prefix }
                            variant={ activeSet === set.prefix ? 'primary' : 'secondary' }
                            size="small"
                            onClick={ () => {
                                setActiveSet( set.prefix );
                                setPage( 1 );
                            } }
                        >
                            { set.label }
                        </Button>
                    ) ) }
                </div>
            ) }

            { recent.length > 0 && (
                <div>
                    <p style={ { margin: '4px 0', fontSize: '11px', textTransform: 'uppercase', opacity: 0.7 } }>
                        { __( 'Recent', 'artisanpack-visual-editor' ) }
                    </p>
                    <div className="wp-block-artisanpack-icon__picker-recent" style={ RECENT_ROW_STYLE } role="list">
                        { recent.map( ( ref ) => (
                            <Button
                                key={ `${ ref.set }:${ ref.name }` }
                                variant="tertiary"
                                size="small"
                                onClick={ () => commitSelection( ref ) }
                                title={ `${ ref.set }:${ ref.name }` }
                            >
                                { ref.name }
                            </Button>
                        ) ) }
                    </div>
                </div>
            ) }

            { loading && (
                <div style={ { textAlign: 'center', padding: '16px' } }>
                    <Spinner />
                </div>
            ) }

            { error && (
                <p role="alert" style={ { color: '#b32d2e' } }>
                    { error }
                </p>
            ) }

            { ! loading && ! error && results.length === 0 && (
                <p style={ { padding: '12px 0', opacity: 0.7 } }>
                    { __( 'No icons matched.', 'artisanpack-visual-editor' ) }
                </p>
            ) }

            { ! loading && ! error && results.length > 0 && (
                /* eslint-disable-next-line jsx-a11y/no-static-element-interactions */
                <div
                    role="grid"
                    aria-label={ __( 'Icon results', 'artisanpack-visual-editor' ) }
                    tabIndex={ 0 }
                    onKeyDown={ handleGridKeyDown }
                    style={ GRID_STYLE }
                    className="wp-block-artisanpack-icon__picker-grid"
                >
                    { results.map( ( icon, index ) => (
                        <button
                            key={ `${ icon.set }:${ icon.name }` }
                            type="button"
                            role="gridcell"
                            style={ index === focusIndex ? GRID_BUTTON_FOCUSED_STYLE : GRID_BUTTON_STYLE }
                            onClick={ () => commitSelection( { set: icon.set, name: icon.name } ) }
                            onMouseEnter={ () => setFocusIndex( index ) }
                            title={ `${ icon.set }:${ icon.name }` }
                            aria-label={ `${ icon.label } (${ icon.set })` }
                        >
                            { icon.svg ? (
                                <span
                                    aria-hidden="true"
                                    style={ { width: '24px', height: '24px', display: 'inline-flex' } }
                                    // Bundled FA SVGs ship from the
                                    // package's own disk — they're
                                    // trusted, the picker is gated to
                                    // authenticated editor users, and
                                    // the IconSvgResolver allowlists
                                    // both set and name. Inlining is
                                    // the only way to recolor via CSS.
                                    dangerouslySetInnerHTML={ { __html: icon.svg } }
                                />
                            ) : (
                                <span>{ icon.label || icon.name }</span>
                            ) }
                        </button>
                    ) ) }
                </div>
            ) }

            { totalPages > 1 && (
                <div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '12px' } }>
                    <Button
                        variant="secondary"
                        size="small"
                        disabled={ page <= 1 }
                        onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
                    >
                        { __( 'Previous', 'artisanpack-visual-editor' ) }
                    </Button>
                    <span style={ { fontSize: '12px', opacity: 0.7 } }>
                        { sprintf(
                            /* translators: 1: current page, 2: total pages, 3: total result count. */
                            __( 'Page %1$d of %2$d (%3$d icons)', 'artisanpack-visual-editor' ),
                            page,
                            totalPages,
                            total
                        ) }
                    </span>
                    <Button
                        variant="secondary"
                        size="small"
                        disabled={ page >= totalPages }
                        onClick={ () => setPage( ( p ) => Math.min( totalPages, p + 1 ) ) }
                    >
                        { __( 'Next', 'artisanpack-visual-editor' ) }
                    </Button>
                </div>
            ) }
        </Modal>
    );
}
