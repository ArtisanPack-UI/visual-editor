/**
 * Integration tests for position support (#647).
 *
 * Round-trip attributes through the resolver + emitter across all
 * breakpoints so a regression in either side surfaces immediately.
 * The unit tests exercise the resolver and emitter in isolation; this
 * file wires them together against realistic block attribute payloads
 * — the shape saved to post_content, the shape re-hydrated on load,
 * the CSS emitted for each cascade level.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { describe, expect, it } from 'vitest'

import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../../responsive/registry'
import {
	emitPositionCss,
	mergedBreakpointLayers,
} from '../emitter'
import {
	rawOffsetAt,
	rawValueAt,
	rawZIndexAt,
	resolveAtBreakpoint,
	resolvePosition,
} from '../resolver'

const registry = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

describe( 'position support — full round-trip across breakpoints', () => {
	it( 'round-trips base + per-breakpoint values through resolver → emitter', () => {
		// Simulates a block a user configured with a base sticky
		// position and progressively broader offsets at md and lg.
		const attributes = {
			style: {
				position: {
					value:   'sticky',
					offsets: { top: { value: 0, unit: 'px' } },
					zIndex:  10,
					_positionScopeId: 'roundtrip1',
				},
			},
			responsive: {
				'style.position': {
					md: { offsets: { top: { value: 8,  unit: 'px' } } },
					lg: { offsets: { top: { value: 16, unit: 'px' } }, zIndex: 20 },
				},
			},
		}

		// Raw readers (what the inspector uses to draw "inherited" chips).
		expect( rawValueAt( attributes, 'base' ) ).toBe( 'sticky' )
		expect( rawValueAt( attributes, 'md' ) ).toBeNull()
		expect( rawZIndexAt( attributes, 'md' ) ).toBeNull()
		expect( rawZIndexAt( attributes, 'lg' ) ).toBe( 20 )
		expect( rawOffsetAt( attributes, 'md', 'top' ) ).toEqual( {
			value: 8, unit: 'px',
		} )

		// Effective layers (what the canvas + frontend render).
		const base = resolveAtBreakpoint( attributes, 'base', registry )!
		expect( base.value ).toBe( 'sticky' )
		expect( base.offsets.top ).toEqual( { value: 0, unit: 'px' } )
		expect( base.zIndex ).toBe( 10 )

		const md = resolveAtBreakpoint( attributes, 'md', registry )!
		// md inherits sticky + zIndex 10 from base, overrides top.
		expect( md.value ).toBe( 'sticky' )
		expect( md.offsets.top ).toEqual( { value: 8, unit: 'px' } )
		expect( md.zIndex ).toBe( 10 )

		const lg = resolveAtBreakpoint( attributes, 'lg', registry )!
		expect( lg.value ).toBe( 'sticky' )
		expect( lg.offsets.top ).toEqual( { value: 16, unit: 'px' } )
		expect( lg.zIndex ).toBe( 20 )

		// Emitter output — same payload → deterministic CSS.
		const payload = resolvePosition( attributes )!
		const merged  = mergedBreakpointLayers( payload, registry )
		const css     = emitPositionCss( '.ve-pos-roundtrip1', payload, registry, merged )

		// Assert declarations individually (order/whitespace-agnostic)
		// so a benign emitter refactor doesn't break the test.
		expect( css ).toContain( '.ve-pos-roundtrip1{' )
		expect( css ).toContain( 'position:sticky !important' )
		expect( css ).toContain( 'top:0px !important' )
		expect( css ).toContain( 'z-index:10 !important' )
		expect( css ).toContain( '@media (min-width:768px){' )
		expect( css ).toContain( 'top:8px !important' )
		expect( css ).toContain( '@media (min-width:1024px){' )
		expect( css ).toContain( 'top:16px !important' )
		expect( css ).toContain( 'z-index:20 !important' )
	} )

	it( 'emits nothing for a block whose base resolves to static + no breakpoints override', () => {
		const attributes = {
			style: { position: { value: 'static' } },
		}
		const payload = resolvePosition( attributes )!
		const merged  = mergedBreakpointLayers( payload, registry )
		const css     = emitPositionCss( '.ve-pos-x', payload, registry, merged )
		expect( css ).toBe( '' )
	} )
} )

describe( 'position support — legacy Gutenberg sticky no-churn', () => {
	it( 'loads and re-serializes a bare "sticky" string without attribute migration', () => {
		// Simulates content saved by Gutenberg's native `supports.position`
		// before the block opted into artisanpack's structured shape.
		// The resolver widens the string; the emitter renders the same
		// sticky rule. No writer needs to touch the attribute — a
		// no-op re-save preserves it byte-for-byte.
		const attributes = { style: { position: 'sticky' } }

		const layer = resolveAtBreakpoint( attributes, 'base', registry )!
		expect( layer.value ).toBe( 'sticky' )
		expect( layer.offsets ).toEqual( {
			top: null, right: null, bottom: null, left: null,
		} )
		expect( layer.zIndex ).toBeNull()

		const payload = resolvePosition( attributes )!
		const merged  = mergedBreakpointLayers( payload, registry )
		expect( emitPositionCss( '.ve-pos-x', payload, registry, merged ) )
			.toBe( '.ve-pos-x{position:sticky !important}' )

		// Attribute is untouched — the block's style.position is still
		// the same string reference.
		expect( attributes.style.position ).toBe( 'sticky' )
	} )
} )

describe( 'position support — orphan fields survive a toggle back to static', () => {
	it( 'preserves offsets and zIndex when value is static, without emitting CSS', () => {
		// Users often flip value=static to preview un-positioned layout
		// then flip back — offsets and zIndex must survive that round
		// trip. The emitter is silent while static (per #643).
		const attributes = {
			style: {
				position: {
					value:   'static',
					offsets: { top: { value: 20, unit: 'px' } },
					zIndex:  5,
				},
			},
		}

		const payload = resolvePosition( attributes )!
		expect( payload.base?.offsets.top ).toEqual( { value: 20, unit: 'px' } )
		expect( payload.base?.zIndex ).toBe( 5 )

		const css = emitPositionCss(
			'.ve-pos-x',
			payload,
			registry,
			mergedBreakpointLayers( payload, registry ),
		)
		expect( css ).toBe( '' )
	} )
} )
