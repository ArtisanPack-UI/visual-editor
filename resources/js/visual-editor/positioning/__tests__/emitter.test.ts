/**
 * Emitter tests for the position feature (#643).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { describe, expect, it } from 'vitest'

import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../../responsive/registry'
import {
	emitPositionCss,
	layerDeclarations,
	mergedBreakpointLayers,
} from '../emitter'
import { resolvePosition } from '../resolver'

const registry = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

describe( 'layerDeclarations', () => {
	it( 'emits nothing for a null layer', () => {
		expect( layerDeclarations( null ) ).toBe( '' )
	} )

	it( 'emits nothing when the resolved value is static', () => {
		expect(
			layerDeclarations( {
				value: 'static',
				offsets: { top: null, right: null, bottom: null, left: null },
				zIndex: null,
			} ),
		).toBe( '' )
	} )

	it( 'emits nothing when only orphan offsets/zIndex are set with no non-static value', () => {
		expect(
			layerDeclarations( {
				value: null,
				offsets: {
					top:    { value: 10, unit: 'px' },
					right:  null,
					bottom: null,
					left:   null,
				},
				zIndex: 3,
			} ),
		).toBe( '' )
	} )

	it( 'emits position + offsets + z-index for a non-static value', () => {
		expect(
			layerDeclarations( {
				value: 'absolute',
				offsets: {
					top:    { value: 10, unit: 'px' },
					right:  null,
					bottom: { value: 5, unit: 'rem' },
					left:   { value: 0, unit: 'auto' },
				},
				zIndex: 2,
			} ),
		).toBe( 'position:absolute !important;top:10px !important;bottom:5rem !important;left:auto !important;z-index:2 !important' )
	} )
} )

describe( 'emitPositionCss', () => {
	it( 'returns empty string when the scope is empty', () => {
		const payload = resolvePosition( { style: { position: { value: 'relative' } } } )!
		expect( emitPositionCss( '   ', payload, registry, {} ) ).toBe( '' )
	} )

	it( 'returns empty string when the payload is null', () => {
		expect( emitPositionCss( '.foo', null, registry, {} ) ).toBe( '' )
	} )

	it( 'emits the base rule and per-breakpoint media queries in ascending order', () => {
		const attrs = {
			style: {
				position: {
					value: 'relative',
					offsets: { top: { value: 10, unit: 'px' } },
				},
			},
			responsive: {
				'style.position': {
					lg: { value: 'sticky', offsets: { top: { value: 0, unit: 'px' } } },
					md: { zIndex: 3 },
				},
			},
		}

		const payload = resolvePosition( attrs )!
		const merged  = mergedBreakpointLayers( payload, registry )
		const css     = emitPositionCss( '.wrap', payload, registry, merged )

		expect( css ).toContain( '.wrap{position:relative !important;top:10px !important}' )
		// md inherits value=relative + top from base, adds z-index.
		expect( css ).toContain(
			'@media (min-width:768px){.wrap{position:relative !important;top:10px !important;z-index:3 !important}}',
		)
		// lg overrides value to sticky and top to 0px.
		expect( css ).toContain(
			'@media (min-width:1024px){.wrap{position:sticky !important;top:0px !important;z-index:3 !important}}',
		)
	} )

	it( 'skips media queries when a breakpoint layer resolves to static-only', () => {
		const attrs = {
			style: { position: { value: 'relative' } },
			responsive: {
				'style.position': {
					lg: { value: 'static' },
				},
			},
		}
		const payload = resolvePosition( attrs )!
		const merged  = mergedBreakpointLayers( payload, registry )
		const css     = emitPositionCss( '.wrap', payload, registry, merged )

		expect( css ).toContain( '.wrap{position:relative !important}' )
		expect( css ).not.toContain( '@media' )
	} )

	it( 'legacy sticky (bare string) round-trips to a single position:sticky rule', () => {
		const payload = resolvePosition( { style: { position: 'sticky' } } )!
		const merged  = mergedBreakpointLayers( payload, registry )
		const css     = emitPositionCss( '.wrap', payload, registry, merged )
		expect( css ).toBe( '.wrap{position:sticky !important}' )
	} )
} )

describe( 'mergedBreakpointLayers', () => {
	it( 'folds each breakpoint on top of the running merged layer', () => {
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

		const payload = resolvePosition( attrs )!
		const merged  = mergedBreakpointLayers( payload, registry )

		expect( merged.md ).toEqual( {
			value: 'absolute',
			offsets: {
				top:    { value: 5, unit: 'px' },
				right:  null,
				bottom: null,
				left:   null,
			},
			zIndex: 9,
		} )
		expect( merged.lg ).toEqual( {
			value: 'sticky',
			offsets: {
				top:    { value: 5, unit: 'px' },
				right:  null,
				bottom: null,
				left:   null,
			},
			zIndex: 9,
		} )
	} )
} )
