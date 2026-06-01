/**
 * Attribute migrator — lazy scalar → discriminated promotion (#487).
 *
 * Mirrors PHP `AttributeMigrator`. Used by the editor's InspectorControls
 * to:
 *  - Promote a scalar attribute into `{base, sm, …}` the first time an
 *    editor sets a value at a non-`base` breakpoint.
 *  - Demote back to a scalar when every override is cleared.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY, type ResponsiveAttribute } from './types'

function isResponsiveObject<T>( attribute: unknown ): attribute is { [BASE_KEY]: T | null } & Record<string, T | null | undefined> {
	if ( null === attribute || 'object' !== typeof attribute || Array.isArray( attribute ) ) {
		return false
	}

	return BASE_KEY in ( attribute as Record<string, unknown> )
}

export function promote<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	breakpoint: string,
	value: T | null,
): ResponsiveAttribute<T> {
	if ( BASE_KEY === breakpoint && ! isResponsiveObject<T>( attribute ) ) {
		return value as T
	}

	const next: Record<string, T | null | undefined> = isResponsiveObject<T>( attribute )
		? { ...( attribute as Record<string, T | null | undefined> ) }
		: { [BASE_KEY]: ( attribute ?? null ) as T | null }

	next[ breakpoint ] = value

	return next as ResponsiveAttribute<T>
}

export function demote<T>( attribute: ResponsiveAttribute<T> | null | undefined ): ResponsiveAttribute<T> | null {
	if ( ! isResponsiveObject<T>( attribute ) ) {
		return ( attribute ?? null ) as ResponsiveAttribute<T> | null
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

	return ( obj[ BASE_KEY ] ?? null ) as ResponsiveAttribute<T> | null
}

export function clearOverride<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	breakpoint: string,
): ResponsiveAttribute<T> | null {
	if ( ! isResponsiveObject<T>( attribute ) ) {
		return ( attribute ?? null ) as ResponsiveAttribute<T> | null
	}

	const next: Record<string, T | null | undefined> = { ...( attribute as Record<string, T | null | undefined> ) }

	if ( BASE_KEY === breakpoint ) {
		next[ BASE_KEY ] = null
	} else {
		delete next[ breakpoint ]
	}

	return demote( next as ResponsiveAttribute<T> )
}
