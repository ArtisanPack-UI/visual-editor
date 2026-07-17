/**
 * Position attribute resolver (#641).
 *
 * Walks a block's attribute tree and produces a normalised payload the
 * emitter + inspector consume. The idle layer lives at
 * `attributes.style.position`; per-breakpoint overrides ride
 * `attributes.responsive['style.position']` following the responsive
 * routing pattern from #487.
 *
 * Backwards compatible with Gutenberg's native sticky path — a plain
 * string at `style.position` (e.g. `'sticky'`) is widened to
 * `{ value: 'sticky' }`. Anything else in the string form falls
 * through unless it matches one of the five supported values.
 *
 * Breakpoint inheritance follows the responsive resolver's mobile-
 * first cascade (larger breakpoints inherit from smaller ones). This
 * mirrors `resolveResponsiveValue` for scalars but operates on the
 * full structured layer so partial overrides at a breakpoint inherit
 * the untouched fields from the next-smaller defined layer.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import type { BreakpointRegistry } from '../responsive/registry'
import { BASE_KEY } from '../responsive/types'
import {
	POSITION_VALUES,
	OFFSET_UNITS,
	OFFSET_SIDES,
	type OffsetSide,
	type OffsetSubtree,
	type OffsetValue,
	type PositionAttributes,
	type PositionSubtree,
	type PositionValue,
	type ResolvedPosition,
	type ResolvedPositionLayer,
} from './types'

const POSITION_VALUE_SET = new Set<string>( POSITION_VALUES )
const OFFSET_UNIT_SET    = new Set<string>( OFFSET_UNITS )

function normalizeValue( raw: unknown ): PositionValue | null {
	if ( 'string' !== typeof raw ) {
		return null
	}

	const trimmed = raw.trim().toLowerCase()

	return POSITION_VALUE_SET.has( trimmed ) ? ( trimmed as PositionValue ) : null
}

function normalizeOffset( raw: unknown ): OffsetValue | null {
	if ( ! raw || 'object' !== typeof raw ) {
		return null
	}

	const record = raw as Record<string, unknown>
	const unit   = 'string' === typeof record.unit ? record.unit.trim().toLowerCase() : null

	if ( null === unit || ! OFFSET_UNIT_SET.has( unit ) ) {
		return null
	}

	// The `auto` unit has no numeric component. Store as `0` so
	// consumers can unconditionally read `value` without null checks;
	// the emitter special-cases `unit === 'auto'` to render `auto`.
	if ( 'auto' === unit ) {
		return { value: 0, unit: 'auto' }
	}

	const numeric = 'number' === typeof record.value
		? record.value
		: ( 'string' === typeof record.value && '' !== record.value.trim()
			? Number( record.value )
			: NaN )

	if ( ! Number.isFinite( numeric ) ) {
		return null
	}

	return { value: numeric, unit: unit as OffsetValue[ 'unit' ] }
}

function normalizeOffsets( raw: unknown ): ResolvedPositionLayer[ 'offsets' ] {
	const out: ResolvedPositionLayer[ 'offsets' ] = {
		top:    null,
		right:  null,
		bottom: null,
		left:   null,
	}

	if ( ! raw || 'object' !== typeof raw ) {
		return out
	}

	const record = raw as Record<string, unknown>

	for ( const side of OFFSET_SIDES ) {
		out[ side ] = normalizeOffset( record[ side ] )
	}

	return out
}

function normalizeZIndex( raw: unknown ): number | null {
	if ( 'number' === typeof raw && Number.isFinite( raw ) ) {
		return Math.trunc( raw )
	}

	if ( 'string' === typeof raw && '' !== raw.trim() ) {
		const parsed = Number( raw )
		if ( Number.isFinite( parsed ) ) {
			return Math.trunc( parsed )
		}
	}

	return null
}

/**
 * Coerce the raw slot at `attributes.style.position` (or an override
 * inside `attributes.responsive`) into a structured subtree. A plain
 * string is treated as the `value` field alone — this is how
 * Gutenberg's native sticky path stores its data.
 */
export function coerceSubtree( raw: unknown ): PositionSubtree | null {
	if ( null === raw || undefined === raw ) {
		return null
	}

	if ( 'string' === typeof raw ) {
		const value = normalizeValue( raw )
		return value ? { value } : null
	}

	if ( 'object' !== typeof raw ) {
		return null
	}

	return raw as PositionSubtree
}

function resolveLayer( subtree: PositionSubtree | null ): ResolvedPositionLayer | null {
	if ( ! subtree ) {
		return null
	}

	const value   = normalizeValue( subtree.value )
	const offsets = normalizeOffsets( subtree.offsets )
	const zIndex  = normalizeZIndex( subtree.zIndex )

	const hasAny =
		null !== value
		|| null !== offsets.top
		|| null !== offsets.right
		|| null !== offsets.bottom
		|| null !== offsets.left
		|| null !== zIndex

	if ( ! hasAny ) {
		return null
	}

	return {
		value,
		offsets,
		zIndex,
	}
}

/**
 * Fold an overlay layer on top of a base layer — overlay wins per
 * field, base fills any nulls. Exported so {@see emitter#mergedBreakpointLayers}
 * and future callers can share the same fallthrough logic (see #640
 * review finding on triplicated merge code).
 */
export function mergeLayers(
	base: ResolvedPositionLayer | null,
	overlay: ResolvedPositionLayer | null,
): ResolvedPositionLayer | null {
	if ( ! overlay ) {
		return base
	}

	if ( ! base ) {
		return overlay
	}

	return {
		value: overlay.value ?? base.value,
		offsets: {
			top:    overlay.offsets.top    ?? base.offsets.top,
			right:  overlay.offsets.right  ?? base.offsets.right,
			bottom: overlay.offsets.bottom ?? base.offsets.bottom,
			left:   overlay.offsets.left   ?? base.offsets.left,
		},
		zIndex: overlay.zIndex ?? base.zIndex,
	}
}

function readIdle( attributes: PositionAttributes ): ResolvedPositionLayer | null {
	const subtree = coerceSubtree( attributes.style?.position )
	return resolveLayer( subtree )
}

function readBreakpointOverrides(
	attributes: PositionAttributes,
): Record<string, ResolvedPositionLayer> {
	const responsive = attributes.responsive

	if ( ! responsive || 'object' !== typeof responsive ) {
		return {}
	}

	const bag = responsive[ 'style.position' ]

	if ( ! bag || 'object' !== typeof bag ) {
		return {}
	}

	const out: Record<string, ResolvedPositionLayer> = {}

	for ( const [ key, raw ] of Object.entries( bag ) ) {
		if ( '' === key || BASE_KEY === key ) {
			continue
		}

		const subtree = coerceSubtree( raw )
		const layer   = resolveLayer( subtree )

		if ( null === layer ) {
			continue
		}

		out[ key ] = layer
	}

	return out
}

/**
 * Resolve a block's attribute tree into the normalised payload the
 * emitter consumes. Returns `null` when no position configuration
 * exists at any cascade level.
 */
export function resolvePosition(
	attributes: PositionAttributes | null | undefined,
): ResolvedPosition | null {
	if ( ! attributes || 'object' !== typeof attributes ) {
		return null
	}

	const base        = readIdle( attributes )
	const breakpoints = readBreakpointOverrides( attributes )

	if ( null === base && 0 === Object.keys( breakpoints ).length ) {
		return null
	}

	return { base, breakpoints }
}

/**
 * Resolve the effective layer for a specific breakpoint. Larger
 * breakpoints inherit from smaller ones; missing fields at the target
 * breakpoint fall through to the next-smaller defined layer.
 *
 * Pass `BASE_KEY` (or any unknown key) to get the base layer alone.
 */
export function resolveAtBreakpoint(
	attributes: PositionAttributes | null | undefined,
	breakpointKey: string,
	registry: BreakpointRegistry,
): ResolvedPositionLayer | null {
	const payload = resolvePosition( attributes )

	if ( null === payload ) {
		return null
	}

	if ( BASE_KEY === breakpointKey || ! registry.has( breakpointKey ) ) {
		return payload.base
	}

	const ordered = registry.keysWithBase()
	const target  = ordered.indexOf( breakpointKey )

	if ( -1 === target ) {
		return payload.base
	}

	let merged: ResolvedPositionLayer | null = payload.base

	for ( let i = 1; i <= target; i++ ) {
		const key = ordered[ i ]
		if ( key in payload.breakpoints ) {
			merged = mergeLayers( merged, payload.breakpoints[ key ] )
		}
	}

	return merged
}

/**
 * Read a single offset side directly from the attributes without
 * booting the resolver. Used by the inspector to distinguish "value
 * inherited from a smaller breakpoint" from "value set at THIS
 * breakpoint."
 */
export function rawOffsetAt(
	attributes: PositionAttributes | null | undefined,
	breakpointKey: string,
	side: OffsetSide,
): OffsetValue | null {
	if ( ! attributes ) {
		return null
	}

	const subtree = BASE_KEY === breakpointKey
		? coerceSubtree( attributes.style?.position )
		: coerceSubtree(
			attributes.responsive?.[ 'style.position' ]?.[ breakpointKey ],
		)

	if ( ! subtree ) {
		return null
	}

	const offsets = subtree.offsets as OffsetSubtree | null | undefined

	if ( ! offsets ) {
		return null
	}

	return normalizeOffset( offsets[ side ] )
}

/**
 * Read the raw `value` field at a specific breakpoint without
 * inheritance. Returns `null` when the block doesn't set position at
 * that breakpoint (or when only the offsets/zIndex are set).
 */
export function rawValueAt(
	attributes: PositionAttributes | null | undefined,
	breakpointKey: string,
): PositionValue | null {
	if ( ! attributes ) {
		return null
	}

	const subtree = BASE_KEY === breakpointKey
		? coerceSubtree( attributes.style?.position )
		: coerceSubtree(
			attributes.responsive?.[ 'style.position' ]?.[ breakpointKey ],
		)

	return subtree ? normalizeValue( subtree.value ) : null
}

/**
 * Read the raw `zIndex` field at a specific breakpoint without
 * inheritance.
 */
export function rawZIndexAt(
	attributes: PositionAttributes | null | undefined,
	breakpointKey: string,
): number | null {
	if ( ! attributes ) {
		return null
	}

	const subtree = BASE_KEY === breakpointKey
		? coerceSubtree( attributes.style?.position )
		: coerceSubtree(
			attributes.responsive?.[ 'style.position' ]?.[ breakpointKey ],
		)

	return subtree ? normalizeZIndex( subtree.zIndex ) : null
}
