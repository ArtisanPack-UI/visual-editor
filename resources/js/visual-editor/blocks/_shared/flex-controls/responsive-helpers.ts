/**
 * Helpers for reading + writing per-breakpoint flex values (#595).
 *
 * Controls read the active breakpoint, resolve the cascaded value for
 * display, and on change write into that breakpoint's slot (preserving
 * other breakpoints).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { BreakpointRegistry } from '../../../responsive/registry'
import { BASE_KEY, type ResponsiveAttribute } from '../../../responsive/types'
import { resolveResponsiveValue } from '../../../responsive/resolver'

export function readAt<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	breakpoint: string,
	registry: BreakpointRegistry,
): T | null {
	return resolveResponsiveValue<T>( attribute ?? null, breakpoint, registry )
}

/**
 * Write `value` into the given breakpoint slot. Returns a new
 * responsive attribute object that preserves every other slot.
 * Passing `undefined`/`null` clears the slot at that breakpoint.
 */
export function writeAt<T>(
	attribute: ResponsiveAttribute<T> | null | undefined,
	breakpoint: string,
	value: T | null | undefined,
): ResponsiveAttribute<T> | null {
	const existing = ( null !== attribute && undefined !== attribute && 'object' === typeof attribute && ! Array.isArray( attribute ) )
		? ( attribute as Record<string, T | null | undefined> )
		: ( null !== attribute && undefined !== attribute )
			? ( { [ BASE_KEY ]: attribute as T } as Record<string, T | null | undefined> )
			: ( {} as Record<string, T | null | undefined> )

	const next = { ...existing }

	if ( null === value || undefined === value ) {
		delete next[ breakpoint ]
	} else {
		next[ breakpoint ] = value
	}

	if ( 0 === Object.keys( next ).length ) {
		return null
	}

	return next as ResponsiveAttribute<T>
}
