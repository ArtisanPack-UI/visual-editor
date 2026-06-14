/**
 * Hooks for loading the binding sources catalog + per-source field lists
 * (#504). One in-memory cache per module — the inspector can be opened
 * on dozens of blocks per session, and we never want to re-hit the
 * sources endpoint for each one.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { useEffect, useState } from 'react';

import { fetchBindingFields, fetchBindingSources } from './api';
import type {
	BindingFieldDefinition,
	BindingSourceSummary,
} from './types';

interface Cache {
	sources: BindingSourceSummary[] | null;
	sourcesPromise: Promise<BindingSourceSummary[]> | null;
	fields: Map<string, BindingFieldDefinition[]>;
	fieldsPromises: Map<string, Promise<BindingFieldDefinition[]>>;
}

const cache: Cache = {
	sources: null,
	sourcesPromise: null,
	fields: new Map(),
	fieldsPromises: new Map(),
};

export function resetBindingSourcesCache(): void {
	cache.sources = null;
	cache.sourcesPromise = null;
	cache.fields = new Map();
	cache.fieldsPromises = new Map();
}

export interface SourcesState {
	sources: BindingSourceSummary[];
	loading: boolean;
	error: Error | null;
}

export function useBindingSources(): SourcesState {
	const [ state, setState ] = useState<SourcesState>( () => ( {
		sources: cache.sources ?? [],
		loading: cache.sources === null,
		error: null,
	} ) );

	useEffect( () => {
		if ( cache.sources !== null ) {
			return;
		}

		let cancelled = false;

		if ( ! cache.sourcesPromise ) {
			cache.sourcesPromise = fetchBindingSources()
				.then( ( sources ) => {
					cache.sources = sources;
					return sources;
				} )
				.catch( ( error: Error ) => {
					cache.sourcesPromise = null;
					throw error;
				} );
		}

		cache.sourcesPromise
			.then( ( sources ) => {
				if ( ! cancelled ) {
					setState( { sources, loading: false, error: null } );
				}
			} )
			.catch( ( error: Error ) => {
				if ( ! cancelled ) {
					setState( { sources: [], loading: false, error } );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	return state;
}

export interface FieldsState {
	fields: BindingFieldDefinition[];
	loading: boolean;
	error: Error | null;
}

function fieldsKey( source: string, resource: string | null | undefined ): string {
	return `${ source }::${ resource ?? '' }`;
}

export function useBindingFields(
	source: string | null,
	resource: string | null | undefined
): FieldsState {
	const [ state, setState ] = useState<FieldsState>( () => ( {
		fields: source ? cache.fields.get( fieldsKey( source, resource ) ) ?? [] : [],
		loading: source !== null && ! cache.fields.has( fieldsKey( source, resource ) ),
		error: null,
	} ) );

	useEffect( () => {
		if ( ! source ) {
			setState( { fields: [], loading: false, error: null } );
			return;
		}

		const key = fieldsKey( source, resource );

		if ( cache.fields.has( key ) ) {
			setState( {
				fields: cache.fields.get( key ) ?? [],
				loading: false,
				error: null,
			} );
			return;
		}

		let cancelled = false;
		setState( { fields: [], loading: true, error: null } );

		let promise = cache.fieldsPromises.get( key );

		if ( ! promise ) {
			promise = fetchBindingFields( source, resource )
				.then( ( fields ) => {
					cache.fields.set( key, fields );
					cache.fieldsPromises.delete( key );
					return fields;
				} )
				.catch( ( error: Error ) => {
					cache.fieldsPromises.delete( key );
					throw error;
				} );
			cache.fieldsPromises.set( key, promise );
		}

		promise
			.then( ( fields ) => {
				if ( ! cancelled ) {
					setState( { fields, loading: false, error: null } );
				}
			} )
			.catch( ( error: Error ) => {
				if ( ! cancelled ) {
					setState( { fields: [], loading: false, error } );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ source, resource ] );

	return state;
}
