/**
 * State value resolver (#488).
 *
 * Mirrors the PHP `StateValueResolver`. Given a stateful
 * `{ idle, hover, focus, … }` attribute and the active state,
 * returns the value the editor / renderer should show.
 *
 *  - `idle` is the base; it applies whenever a more specific state
 *    has no explicit override.
 *  - `null` (or missing) means "inherit from the next link in the
 *    inheritance chain."
 *  - Scalars round-trip unchanged.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import type { StateRegistry } from './registry'
import { BASE_KEY, type StatefulAttribute } from './types'

export function isStatefulAttribute<T>(
	value: unknown,
	registry: StateRegistry,
): value is { [k: string]: T | null } {
	if ( null === value || 'object' !== typeof value || Array.isArray( value ) ) {
		return false
	}

	const obj = value as Record<string, unknown>

	if ( BASE_KEY in obj ) {
		return true
	}

	return registry.keys().some( ( key ) => BASE_KEY !== key && key in obj )
}

export function resolveStateValue<T>(
	attribute: StatefulAttribute<T> | null | undefined,
	activeState: string,
	registry: StateRegistry,
): T | null {
	if ( null === attribute || undefined === attribute ) {
		return null
	}

	if ( ! isStatefulAttribute<T>( attribute, registry ) ) {
		return attribute as T
	}

	const chain = registry.inheritanceChain( activeState )
	const obj   = attribute as Record<string, T | null | undefined>

	for ( const key of chain ) {
		if ( ! ( key in obj ) ) {
			continue
		}

		const value = obj[ key ]
		if ( null === value || undefined === value ) {
			continue
		}

		return value
	}

	return null
}

/**
 * Returns a state-keyed map of only the states whose resolved value
 * differs from their inheritance parent. `idle` is always included
 * when it carries a non-null value. Used by renderers to avoid
 * emitting redundant CSS rules.
 */
export function distinctStateOverrides<T>(
	attribute: StatefulAttribute<T> | null | undefined,
	registry: StateRegistry,
): Record<string, T> {
	if ( null === attribute || undefined === attribute ) {
		return {}
	}

	const normalized = isStatefulAttribute<T>( attribute, registry )
		? ( attribute as Record<string, T | null | undefined> )
		: ( { [BASE_KEY]: attribute } as Record<string, T | null | undefined> )

	const out: Record<string, T> = {}

	for ( const state of registry.keys() ) {
		const value = resolveStateValue<T>( normalized, state, registry )
		if ( null === value ) {
			continue
		}

		if ( BASE_KEY === state ) {
			out[ state ] = value
			continue
		}

		const definition = registry.get( state )
		const parent     = definition?.inheritsFrom ?? BASE_KEY
		const parentVal  = resolveStateValue<T>( normalized, parent, registry )

		if ( value !== parentVal ) {
			out[ state ] = value
		}
	}

	return out
}
