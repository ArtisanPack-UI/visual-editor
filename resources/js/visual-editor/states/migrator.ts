/**
 * State attribute migrator — lazy scalar → stateful promotion (#488).
 *
 * Mirrors PHP `StateAttributeMigrator`. Used by the editor's
 * InspectorControls to:
 *  - Promote a scalar attribute into `{ idle, hover, … }` the first
 *    time an editor sets a value at a non-`idle` state.
 *  - Demote back to a scalar when every override is cleared.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY, type StatefulAttribute } from './types'

function isStatefulObject<T>(
	attribute: unknown,
): attribute is { [BASE_KEY]: T | null } & Record<string, T | null | undefined> {
	if ( null === attribute || 'object' !== typeof attribute || Array.isArray( attribute ) ) {
		return false
	}

	return BASE_KEY in ( attribute as Record<string, unknown> )
}

export function promote<T>(
	attribute: StatefulAttribute<T> | null | undefined,
	state: string,
	value: T | null,
): StatefulAttribute<T> {
	if ( BASE_KEY === state && ! isStatefulObject<T>( attribute ) ) {
		return value as T
	}

	const next: Record<string, T | null | undefined> = isStatefulObject<T>( attribute )
		? { ...( attribute as Record<string, T | null | undefined> ) }
		: { [BASE_KEY]: ( attribute ?? null ) as T | null }

	next[ state ] = value

	return next as StatefulAttribute<T>
}

export function demote<T>(
	attribute: StatefulAttribute<T> | null | undefined,
): StatefulAttribute<T> | null {
	if ( ! isStatefulObject<T>( attribute ) ) {
		return ( attribute ?? null ) as StatefulAttribute<T> | null
	}

	const obj = attribute as Record<string, T | null | undefined>

	for ( const key of Object.keys( obj ) ) {
		if ( BASE_KEY === key ) {
			continue
		}

		if ( null !== obj[ key ] && undefined !== obj[ key ] ) {
			return attribute
		}
	}

	return ( obj[ BASE_KEY ] ?? null ) as StatefulAttribute<T> | null
}

export function clearOverride<T>(
	attribute: StatefulAttribute<T> | null | undefined,
	state: string,
): StatefulAttribute<T> | null {
	if ( ! isStatefulObject<T>( attribute ) ) {
		return ( attribute ?? null ) as StatefulAttribute<T> | null
	}

	const next: Record<string, T | null | undefined> = { ...( attribute as Record<string, T | null | undefined> ) }

	if ( BASE_KEY === state ) {
		next[ BASE_KEY ] = null
	} else {
		delete next[ state ]
	}

	return demote( next as StatefulAttribute<T> )
}
