/**
 * Shared types for CSS positioning support (#640).
 *
 * Extends Gutenberg's `supports.position` — the native shape stores
 * `attributes.style.position` as a plain string (`'sticky'`). We
 * layer a structured object on top of the same attribute path so the
 * resolver can read either shape (see `resolver.ts`).
 *
 * Per-breakpoint overrides ride `attributes.responsive['style.position']`
 * following the routing pattern established in #487 and mirrored in
 * `box-shadows/types.ts` from #607.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

export type PositionValue =
	| 'static'
	| 'relative'
	| 'absolute'
	| 'fixed'
	| 'sticky'

export type OffsetUnit = 'px' | '%' | 'rem' | 'em' | 'vh' | 'vw' | 'auto'

export type OffsetSide = 'top' | 'right' | 'bottom' | 'left'

export interface OffsetValue {
	value: number
	unit: OffsetUnit
}

export interface OffsetSubtree {
	top?: OffsetValue | null
	right?: OffsetValue | null
	bottom?: OffsetValue | null
	left?: OffsetValue | null
}

/**
 * Structured position payload written by the inspector. When
 * `attributes.style.position` is a plain string (legacy Gutenberg
 * sticky), the resolver widens it to `{ value: 'sticky' }`.
 */
export interface PositionSubtree {
	value?: PositionValue | null
	offsets?: OffsetSubtree | null
	zIndex?: number | null
	[key: string]: unknown
}

/**
 * Fully resolved layer at a given cascade level (base or breakpoint).
 * Absent fields collapse to `null` so the emitter can skip them.
 */
export interface ResolvedPositionLayer {
	value: PositionValue | null
	offsets: {
		top: OffsetValue | null
		right: OffsetValue | null
		bottom: OffsetValue | null
		left: OffsetValue | null
	}
	zIndex: number | null
}

export interface ResolvedPosition {
	base: ResolvedPositionLayer | null
	breakpoints: Record<string, ResolvedPositionLayer>
}

/**
 * The subset of block attributes the resolver reads. Blocks carry
 * plenty of other attributes; only the slots we touch are typed.
 */
export interface PositionAttributes {
	// The tree is defensive — the resolver normalizes anything.
	// Slots we care about are typed loosely so test-time inline literals
	// (whose `unit: 'px'` widens to `string`) round-trip without casts.
	style?: { position?: unknown } | null
	responsive?: Record<string, Record<string, unknown>> | null
	[key: string]: unknown
}

export const POSITION_VALUES: readonly PositionValue[] = [
	'static',
	'relative',
	'absolute',
	'fixed',
	'sticky',
]

export const OFFSET_UNITS: readonly OffsetUnit[] = [
	'px',
	'%',
	'rem',
	'em',
	'vh',
	'vw',
	'auto',
]

export const OFFSET_SIDES: readonly OffsetSide[] = [
	'top',
	'right',
	'bottom',
	'left',
]

export const POSITION_ATTRIBUTE_PATH = 'style.position'
