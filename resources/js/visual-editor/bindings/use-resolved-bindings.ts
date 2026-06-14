/**
 * Hook that resolves a block's bindings against the editor's current
 * context (#504). Returns a record of resolved values the HOC overlays
 * on top of the block's static `attrs` so the canvas shows what the
 * frontend renderer will show.
 *
 * Debounces calls — typing a path / picking a different field fires
 * setAttributes every keystroke, and we don't want to spam the
 * `bindings/resolve` endpoint for every intermediate state.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { useEffect, useRef, useState } from 'react';

import { resolveBindings } from './api';
import type { BindingsMap } from './types';

const DEBOUNCE_MS = 250;

export interface ResolvedBindingsState {
	values: Record<string, unknown>;
	loading: boolean;
	error: Error | null;
}

function hasActiveBindings( bindings: BindingsMap | undefined ): boolean {
	if ( ! bindings ) {
		return false;
	}

	for ( const binding of Object.values( bindings ) ) {
		const source = binding?.source;
		if ( typeof source === 'string' && source.length > 0 ) {
			return true;
		}
	}

	return false;
}

export function useResolvedBindings(
	bindings: BindingsMap | undefined,
	attrs: Record<string, unknown>,
	resource: string | null,
	id: number | string | null,
): ResolvedBindingsState {
	const [ state, setState ] = useState<ResolvedBindingsState>( {
		values: {},
		loading: false,
		error: null,
	} );

	const lastSerializedRef = useRef<string | null>( null );

	useEffect( () => {
		if ( ! hasActiveBindings( bindings ) ) {
			lastSerializedRef.current = null;
			setState( { values: {}, loading: false, error: null } );
			return;
		}

		if ( ! resource || id === null ) {
			lastSerializedRef.current = null;
			setState( { values: {}, loading: false, error: null } );
			return;
		}

		// Hash the inputs so re-renders that don't change the binding
		// payload don't re-fire the request.
		const serialized = JSON.stringify( { bindings, attrs, resource, id } );

		if ( serialized === lastSerializedRef.current ) {
			return;
		}

		lastSerializedRef.current = serialized;

		let cancelled = false;
		const controller = new AbortController();

		setState( ( prev ) => ( { values: prev.values, loading: true, error: null } ) );

		const handle = setTimeout( () => {
			resolveBindings(
				{
					attrs,
					bindings: bindings ?? {},
					context: { resource, id },
				},
				controller.signal,
			)
				.then( ( values ) => {
					if ( ! cancelled ) {
						setState( { values, loading: false, error: null } );
					}
				} )
				.catch( ( error: Error ) => {
					if ( ! cancelled && error.name !== 'AbortError' ) {
						setState( { values: {}, loading: false, error } );
					}
				} );
		}, DEBOUNCE_MS );

		return () => {
			cancelled = true;
			controller.abort();
			clearTimeout( handle );
		};
	// `bindings` and `attrs` arrive as new object refs on every parent
	// render — Gutenberg rebuilds the attributes prop frequently, and
	// the panel renders `current` as a fresh `{}` literal each time
	// when no bindings exist. Listing the raw refs here causes the
	// effect to fire on every render; the early-return path then calls
	// `setState({values: {}, ...})` with a fresh object literal, which
	// React sees as a state change and re-renders → infinite loop.
	// Hashing the inputs into a single string deps entry sidesteps
	// that: string equality is by value, so identical content produces
	// identical deps and React skips the effect.
	}, [ JSON.stringify( bindings ), JSON.stringify( attrs ), resource, id ] );

	return state;
}
