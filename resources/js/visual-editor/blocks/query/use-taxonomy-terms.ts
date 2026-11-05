/**
 * `useTaxonomyTerms` — loads a taxonomy's terms for the Query Loop's
 * Filters panel from `GET {apiBase}/taxonomies/{taxonomy}/terms` (#1029),
 * under the API base the host configured.
 *
 * @since 1.20.0
 */

import { useEffect, useState } from 'react';

import { getBindingsApiConfig } from '../../bindings/config';

export interface TaxonomyTerm {
    id: number;
    name: string;
}

export interface TaxonomyTermsState {
    status: 'idle' | 'loading' | 'ready' | 'error';
    terms: TaxonomyTerm[];
}

interface LoadedTerms extends TaxonomyTermsState {
    taxonomy: string;
}

function isTerm( value: unknown ): value is TaxonomyTerm {
    if ( value === null || typeof value !== 'object' ) {
        return false;
    }

    const { id, name } = value as { id?: unknown; name?: unknown };

    return typeof id === 'number' && typeof name === 'string';
}

/**
 * Returns the terms of `taxonomy`, or the idle state for an empty slug.
 * Changing the slug aborts the request in flight.
 *
 * @since 1.20.0
 */
export function useTaxonomyTerms( taxonomy: string ): TaxonomyTermsState {
    const [ loaded, setLoaded ] = useState<LoadedTerms | null>( null );

    useEffect( () => {
        if ( taxonomy === '' ) {
            return;
        }

        const controller = new AbortController();

        fetch( `${ getBindingsApiConfig().apiBase }/taxonomies/${ encodeURIComponent( taxonomy ) }/terms`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        } )
            .then( async ( response ) => {
                if ( ! response.ok ) {
                    throw new Error( `HTTP ${ response.status }` );
                }

                const body = ( await response.json() ) as { data?: unknown };

                if ( ! controller.signal.aborted ) {
                    setLoaded( {
                        taxonomy,
                        status: 'ready',
                        terms: Array.isArray( body.data ) ? body.data.filter( isTerm ) : [],
                    } );
                }
            } )
            .catch( () => {
                if ( ! controller.signal.aborted ) {
                    setLoaded( { taxonomy, status: 'error', terms: [] } );
                }
            } );

        return () => controller.abort();
    }, [ taxonomy ] );

    // Compared on read so a new slug never shows the previous taxonomy's terms.
    if ( loaded?.taxonomy === taxonomy ) {
        return loaded;
    }

    return { status: taxonomy === '' ? 'idle' : 'loading', terms: [] };
}
