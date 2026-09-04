/**
 * Shared editor-side hook for the `artisanpack/business-*` block cluster (#761).
 *
 * The four business-info blocks (business-hours, business-address,
 * business-phone, business-email) each render a slice of one host-supplied
 * envelope resolved through the `ap.visualEditor.businessInfo` filter. On
 * the front end that envelope is stamped onto every block by
 * `BusinessInfoResolver::stampTree()`; in the editor we fetch it once from
 * `GET /visual-editor/api/business-info` and share the result across all
 * four block edit components so a page with many business-info blocks
 * costs one request rather than four (or four per block, per instance).
 *
 * The envelope is deliberately not routed through the core-data shim's
 * entity registry — `entity-placeholder-edit.tsx` is built for simple
 * text / html / image kinds and each business-info block renders a
 * differently-shaped chunk (a hours table, an address + map iframe, a
 * tel: link, a mailto: link). A plain in-flight-cached fetch matches
 * this cluster's needs without wedging a fourth entity kind into the
 * shim purely for one endpoint.
 *
 * Resolution priority a caller should honour:
 *  1. The stamped `_resolvedBusinessInfo` attribute (front-end / saved-tree path).
 *  2. The envelope fetched from the API endpoint (editor WYSIWYG path).
 *  3. The block's own placeholder markup (empty-envelope fallback).
 *
 * Consumers pick the highest available and render — see the four
 * `business-*` block edit.tsx files for the pattern.
 */

import { useEffect, useMemo, useState } from 'react';

/**
 * Shape of the resolved business-info envelope emitted by
 * `GET /visual-editor/api/business-info` and stamped onto a block as
 * `_resolvedBusinessInfo` by the front-end resolver. Kept intentionally
 * loose (readonly + `unknown` for nested shapes) so a host filter that
 * returns extra keys does not require the type to change every time.
 */
export interface BusinessInfoAddress {
    readonly street?: string;
    readonly street2?: string;
    readonly city?: string;
    readonly region?: string;
    readonly postal_code?: string;
    readonly country?: string;
}

export interface BusinessInfoSpecialHours {
    readonly date: string;
    readonly label?: string;
    readonly closed?: boolean;
    readonly open?: string;
    readonly close?: string;
}

export interface BusinessInfoEnvelope {
    readonly address?: BusinessInfoAddress;
    readonly phone?: string;
    readonly email?: string;
    readonly hours?: Readonly<Record<string, unknown>>;
    readonly specialHours?: ReadonlyArray<BusinessInfoSpecialHours>;
    readonly latitude?: number | null;
    readonly longitude?: number | null;
    readonly mapEmbedUrl?: string | null;
}

interface UseBusinessInfoResult {
    readonly envelope: BusinessInfoEnvelope | null;
    readonly loading: boolean;
    readonly error: Error | null;
}

const DEFAULT_API_BASE = '/visual-editor/api';

/**
 * Module-level cache keyed by the fully-qualified endpoint URL so a page
 * with many business-info blocks (four of them side-by-side, say) issues
 * exactly one network request per URL variant. Also survives a component
 * unmount/remount cycle inside the same editor session.
 */
const cache: Map<string, Promise<BusinessInfoEnvelope>> = new Map();

interface UseBusinessInfoOptions {
    /** Optional overrides forwarded as query parameters. */
    readonly mapProvider?: 'osm' | 'google' | 'none';
    readonly showMap?: boolean;
    readonly zoom?: number;
    readonly specialHoursWindowDays?: number;
    /** Test seam — pass a custom fetcher. */
    readonly fetcher?: typeof fetch;
    /** Test seam — override the API base. */
    readonly apiBase?: string;
}

function buildUrl( base: string, options: UseBusinessInfoOptions ): string {
    const trimmed = base.replace( /\/$/, '' );
    const params  = new URLSearchParams();

    if ( options.mapProvider !== undefined ) {
        params.set( 'mapProvider', options.mapProvider );
    }

    if ( options.showMap !== undefined ) {
        params.set( 'showMap', options.showMap ? '1' : '0' );
    }

    if ( options.zoom !== undefined ) {
        params.set( 'zoom', String( options.zoom ) );
    }

    if ( options.specialHoursWindowDays !== undefined ) {
        params.set( 'specialHoursWindowDays', String( options.specialHoursWindowDays ) );
    }

    const query = params.toString();

    return '' === query
        ? `${ trimmed }/business-info`
        : `${ trimmed }/business-info?${ query }`;
}

async function fetchEnvelope( url: string, fetcher: typeof fetch ): Promise<BusinessInfoEnvelope> {
    const response = await fetcher( url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    } );

    if ( ! response.ok ) {
        throw new Error( `business-info fetch failed: ${ response.status }` );
    }

    const body = ( await response.json() ) as BusinessInfoEnvelope;

    return body;
}

/**
 * Fetch the singleton business-info envelope, sharing the in-flight
 * promise across every caller of this hook so a page with many
 * business-info blocks costs one network request per URL variant.
 *
 * Returns `{ envelope: null, loading: true }` while the fetch is in
 * flight so callers can render a placeholder without blocking the
 * block's own chrome (Inspector controls, wrapper attributes).
 */
export function useBusinessInfo( options: UseBusinessInfoOptions = {} ): UseBusinessInfoResult {
    // Memoize the default fetcher so `fetch.bind( globalThis )` is not
    // reallocated on every render. Without this, the `useEffect` below
    // sees a fresh `fetcher` reference each render, re-runs, calls
    // setLoading, and re-renders → "Too many re-renders" crash in
    // every editor view that doesn't pass an explicit `fetcher`.
    const fetcher = useMemo<typeof fetch | null>(
        () =>
            options.fetcher
                ?? ( typeof fetch === 'function' ? fetch.bind( globalThis ) : null ),
        [ options.fetcher ]
    );
    const apiBase = options.apiBase ?? DEFAULT_API_BASE;

    const url = buildUrl( apiBase, options );

    const [ envelope, setEnvelope ] = useState<BusinessInfoEnvelope | null>( null );
    const [ loading, setLoading ]   = useState<boolean>( true );
    const [ error, setError ]       = useState<Error | null>( null );

    useEffect( () => {
        if ( fetcher === null ) {
            setLoading( false );
            setError( new Error( 'fetch is not available in this environment' ) );
            return;
        }

        let cancelled = false;

        let promise = cache.get( url );

        if ( promise === undefined ) {
            promise = fetchEnvelope( url, fetcher );
            cache.set( url, promise );
        }

        // Capture the promise we installed / found so the failure
        // cleanup below can only evict its own entry — a second call
        // that installed a NEW cached entry between this promise's
        // rejection and the cleanup must not have its entry evicted.
        const thisPromise = promise;

        setLoading( true );
        setError( null );

        promise
            .then( ( next ) => {
                if ( cancelled ) {
                    return;
                }
                setEnvelope( next );
                setLoading( false );
            } )
            .catch( ( err: unknown ) => {
                // Drop the cached failure so a follow-up mount can
                // retry rather than being stuck on the same error
                // forever — but only if the cache still points at
                // OUR promise. Otherwise a later caller has already
                // replaced the entry with a fresh in-flight fetch
                // and we would evict their pending work.
                if ( cache.get( url ) === thisPromise ) {
                    cache.delete( url );
                }

                if ( cancelled ) {
                    return;
                }
                setError( err instanceof Error ? err : new Error( String( err ) ) );
                setLoading( false );
            } );

        return () => {
            cancelled = true;
        };
    }, [ url, fetcher ] );

    return { envelope, loading, error };
}

/**
 * Test-only cache reset. Not part of the public export surface.
 * @internal
 */
export function __resetBusinessInfoCache(): void {
    cache.clear();
}
