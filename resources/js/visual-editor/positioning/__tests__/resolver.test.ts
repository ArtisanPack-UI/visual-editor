/**
 * Resolver tests for the position feature (#641).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { describe, expect, it } from 'vitest'

import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../../responsive/registry'
import {
	coerceSubtree,
	rawOffsetAt,
	rawValueAt,
	rawZIndexAt,
	resolveAtBreakpoint,
	resolvePosition,
} from '../resolver'

const registry = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

describe( 'coerceSubtree', () => {
	it( 'widens a legacy sticky string into a structured subtree', () => {
		expect( coerceSubtree( 'sticky' ) ).toEqual( { value: 'sticky' } )
	} )

	it( 'rejects strings that are not one of the five supported values', () => {
		expect( coerceSubtree( 'floaty' ) ).toBeNull()
	} )

	it( 'returns structured objects unchanged', () => {
		const input = { value: 'absolute', zIndex: 5 } as const
		expect( coerceSubtree( input ) ).toBe( input )
	} )

	it( 'returns null for null / undefined / non-string primitives', () => {
		expect( coerceSubtree( null ) ).toBeNull()
		expect( coerceSubtree( undefined ) ).toBeNull()
		expect( coerceSubtree( 42 ) ).toBeNull()
	} )
} )

describe( 'resolvePosition', () => {
	it( 'returns null when no position data exists', () => {
		expect( resolvePosition( {} ) ).toBeNull()
		expect( resolvePosition( { style: {} } ) ).toBeNull()
		expect( resolvePosition( { style: { position: null } } ) ).toBeNull()
	} )

	it( 'resolves the base layer from a structured subtree', () => {
		const attrs = {
			style: {
				position: {
					value: 'absolute',
					offsets: { top: { value: 10, unit: 'px' } },
					zIndex: 3,
				},
			},
		}

		expect( resolvePosition( attrs ) ).toEqual( {
			base: {
				value: 'absolute',
				offsets: {
					top:    { value: 10, unit: 'px' },
					right:  null,
					bottom: null,
					left:   null,
				},
				zIndex: 3,
			},
			breakpoints: {},
		} )
	} )

	it( 'preserves Gutenberg legacy sticky (bare string) at the base layer', () => {
		const attrs = { style: { position: 'sticky' } }

		expect( resolvePosition( attrs ) ).toEqual( {
			base: {
				value: 'sticky',
				offsets: { top: null, right: null, bottom: null, left: null },
				zIndex: null,
			},
			breakpoints: {},
		} )
	} )

	it( 'reads per-breakpoint overrides from the responsive bag', () => {
		const attrs = {
			style: { position: { value: 'relative' } },
			responsive: {
				'style.position': {
					md: { value: 'absolute', zIndex: 2 },
				},
			},
		}

		const resolved = resolvePosition( attrs )!
		expect( resolved.base?.value ).toBe( 'relative' )
		expect( resolved.breakpoints.md?.value ).toBe( 'absolute' )
		expect( resolved.breakpoints.md?.zIndex ).toBe( 2 )
	} )

	it( 'drops entries under the `base` key inside the responsive bag', () => {
		const attrs = {
			responsive: {
				'style.position': {
					base: { value: 'fixed' },
					lg:   { value: 'sticky' },
				},
			},
		}

		const resolved = resolvePosition( attrs )!
		expect( resolved.breakpoints ).toEqual( {
			lg: {
				value: 'sticky',
				offsets: { top: null, right: null, bottom: null, left: null },
				zIndex: null,
			},
		} )
	} )

	it( 'drops offsets with a missing or invalid unit', () => {
		const attrs = {
			style: {
				position: {
					value: 'absolute',
					offsets: {
						top:    { value: 10 },
						right:  { value: 5, unit: 'garbage' },
						bottom: { value: 12, unit: 'rem' },
					},
				},
			},
		}

		const resolved = resolvePosition( attrs )!
		expect( resolved.base?.offsets.top ).toBeNull()
		expect( resolved.base?.offsets.right ).toBeNull()
		expect( resolved.base?.offsets.bottom ).toEqual( { value: 12, unit: 'rem' } )
	} )

	it( 'accepts the `auto` unit without a numeric value', () => {
		const attrs = {
			style: {
				position: {
					value: 'absolute',
					offsets: { top: { unit: 'auto' } },
				},
			},
		}

		const resolved = resolvePosition( attrs )!
		expect( resolved.base?.offsets.top ).toEqual( { value: 0, unit: 'auto' } )
	} )
} )

describe( 'resolveAtBreakpoint', () => {
	it( 'returns the base layer for the base key', () => {
		const attrs = { style: { position: { value: 'relative' } } }
		const layer = resolveAtBreakpoint( attrs, 'base', registry )
		expect( layer?.value ).toBe( 'relative' )
	} )

	it( 'inherits fields from smaller breakpoints when overriding a subset', () => {
		const attrs = {
			style: {
				position: {
					value: 'absolute',
					offsets: { top: { value: 5, unit: 'px' } },
					zIndex: 1,
				},
			},
			responsive: {
				'style.position': {
					md: { zIndex: 9 },
					lg: { value: 'sticky' },
				},
			},
		}

		const md = resolveAtBreakpoint( attrs, 'md', registry )!
		// Overrides zIndex, inherits value + top offset from base.
		expect( md.value ).toBe( 'absolute' )
		expect( md.zIndex ).toBe( 9 )
		expect( md.offsets.top ).toEqual( { value: 5, unit: 'px' } )

		const lg = resolveAtBreakpoint( attrs, 'lg', registry )!
		// Overrides value at lg, inherits zIndex from md and top from base.
		expect( lg.value ).toBe( 'sticky' )
		expect( lg.zIndex ).toBe( 9 )
		expect( lg.offsets.top ).toEqual( { value: 5, unit: 'px' } )
	} )

	it( 'returns null when nothing is defined anywhere', () => {
		expect( resolveAtBreakpoint( {}, 'md', registry ) ).toBeNull()
	} )
} )

describe( 'raw* readers (no inheritance)', () => {
	const attrs = {
		style: {
			position: {
				value: 'relative',
				offsets: { top: { value: 4, unit: 'px' } },
				zIndex: 1,
			},
		},
		responsive: {
			'style.position': {
				md: { zIndex: 9 },
			},
		},
	}

	it( 'rawValueAt only returns explicit values at each breakpoint', () => {
		expect( rawValueAt( attrs, 'base' ) ).toBe( 'relative' )
		expect( rawValueAt( attrs, 'md' ) ).toBeNull()
	} )

	it( 'rawZIndexAt returns the explicit z-index at each breakpoint', () => {
		expect( rawZIndexAt( attrs, 'base' ) ).toBe( 1 )
		expect( rawZIndexAt( attrs, 'md' ) ).toBe( 9 )
		expect( rawZIndexAt( attrs, 'lg' ) ).toBeNull()
	} )

	it( 'rawOffsetAt returns the explicit offset at each breakpoint', () => {
		expect( rawOffsetAt( attrs, 'base', 'top' ) ).toEqual( {
			value: 4,
			unit:  'px',
		} )
		expect( rawOffsetAt( attrs, 'md', 'top' ) ).toBeNull()
	} )
} )
