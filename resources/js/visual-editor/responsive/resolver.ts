/**
 * Responsive value resolver (#487, #820).
 *
 * Mirrors the PHP `ResponsiveValueResolver`. Given a discriminated
 * `{base, tablet, mobile, …}` attribute and the active breakpoint,
 * returns the value the editor / renderer should show.
 *
 *  - `base` is the desktop value; it applies everywhere unless a device
 *    override matches.
 *  - Device keys cascade downward: Mobile inherits from Tablet, then
 *    `base`. Legacy mobile-first keys (`sm`, `md`, …) keep their
 *    min-width cascade.
 *  - `null` (or missing) means "inherit".
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

	return firstDefined( attribute as Record<string, T | null | undefined>, registry.cascade( activeBreakpoint ) )
}

function firstDefined<T>( obj: Record<string, T | null | undefined>, cascade: string[] ): T | null {
	for ( const key of cascade ) {
		const value = obj[ key ]

		if ( key in obj && null !== value && undefined !== value ) {
			return value
		}
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

	// Legacy mobile-first keys compare up the min-width chain, exactly as
	// before #820, so saved markup built from them is unchanged.
	for ( const key of [ BASE_KEY, ...registry.legacyPrefixes() ] ) {
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

	// Desktop-first device keys compare down the max-width chain from base.
	// With legacy keys also set, a device value equal to base may still be
	// needed to override a legacy min-width rule, so keep device values.
	const hasLegacy = registry.legacyPrefixes().some(
		( key ) => null !== normalized[ key ] && undefined !== normalized[ key ],
	)
	previous = resolveResponsiveValue<T>( normalized, BASE_KEY, registry )

	for ( const key of registry.devicePrefixes() ) {
		const value = hasLegacy
			? ( normalized[ key ] ?? null )
			: firstDefined( normalized, registry.cascade( key, false ) )

		if ( null === value ) {
			continue
		}

		if ( hasLegacy || value !== previous ) {
			out[ key ] = value
		}

		previous = value
	}

	return out
}
