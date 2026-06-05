/**
 * Mobile-first responsive value resolver (#487).
 *
 * Mirrors the PHP `ResponsiveValueResolver`. Given a discriminated
 * `{base, sm, md, …}` attribute and the active breakpoint, returns
 * the value the editor / renderer should show.
 *
 *  - `base` is the unprefixed value; it applies everywhere unless a
 *    larger breakpoint overrides it.
 *  - `null` (or missing) means "inherit from the next smaller
 *    defined slot."
 *  - Scalars round-trip unchanged.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import type { BreakpointRegistry } from './registry'
import { BASE_KEY, type ResponsiveAttribute } from './types'

export function isResponsiveAttribute<T>( value: unknown, registry: BreakpointRegistry ): value is { [k: string]: T | null } {
	if ( null === value || 'object' !== typeof value || Array.isArray( value ) ) {
		return false
	}

	const obj = value as Record<string, unknown>

	if ( BASE_KEY in obj ) {
		return true
	}

	return registry.prefixes().some( ( key ) => key in obj )
}

export function resolveResponsiveValue<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	activeBreakpoint: string,
	registry: BreakpointRegistry,
): T | null {
	if ( null === attribute || undefined === attribute ) {
		return null
	}

	if ( ! isResponsiveAttribute<T>( attribute, registry ) ) {
		return attribute as T
	}

	const cascade = cascadeKeys( activeBreakpoint, registry )
	const obj     = attribute as Record<string, T | null | undefined>

	for ( const key of cascade ) {
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

export function distinctOverrides<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	registry: BreakpointRegistry,
): Record<string, T> {
	if ( null === attribute || undefined === attribute ) {
		return {}
	}

	const normalized = isResponsiveAttribute<T>( attribute, registry )
		? ( attribute as Record<string, T | null | undefined> )
		: ( { [BASE_KEY]: attribute } as Record<string, T | null | undefined> )

	const out: Record<string, T> = {}
	let previous: T | null       = null
	let first                    = true

	for ( const key of registry.keysWithBase() ) {
		const value = resolveResponsiveValue<T>( normalized, key, registry )

		if ( null === value ) {
			continue
		}

		if ( first || value !== previous ) {
			out[ key ] = value
			previous   = value
			first      = false
		}
	}

	return out
}

function cascadeKeys( activeBreakpoint: string, registry: BreakpointRegistry ): string[] {
	const ordered = registry.keysWithBase()

	if ( BASE_KEY === activeBreakpoint || ! registry.has( activeBreakpoint ) ) {
		return [ BASE_KEY ]
	}

	const activeIndex = ordered.indexOf( activeBreakpoint )
	if ( -1 === activeIndex ) {
		return [ BASE_KEY ]
	}

	return ordered.slice( 0, activeIndex + 1 ).reverse()
}
