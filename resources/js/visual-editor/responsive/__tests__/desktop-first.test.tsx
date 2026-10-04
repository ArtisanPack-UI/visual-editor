/**
 * Desktop-first device overrides across the editor (#820): the
 * responsive-attributes overlay, the scope chip copy, and the style
 * emitters all cascade downward and emit `max-width` rules, tablet
 * before mobile.
 */

import { act, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType: () => undefined,
	hasBlockSupport: () => false,
} ) )

import { emitBoxShadowCss } from '../../box-shadows/emitter'
import type { ResolvedShadowLayer } from '../../box-shadows/types'
import { emitGradientBorderCss } from '../../gradient-borders/emitter'
import { emitPositionCss, mergedBreakpointLayers } from '../../positioning/emitter'
import { resolveAtBreakpoint, resolvePosition } from '../../positioning/resolver'
import { DEFAULT_STATES, StateRegistry } from '../../states/registry'
import { resetActiveBreakpoint, setActiveBreakpoint } from '../active-breakpoint'
import { InspectorScopeChip } from '../InspectorScopeChip'
import { BreakpointRegistry } from '../registry'
import { buildOverlay } from '../with-responsive-attributes'

const registry = new BreakpointRegistry()
const states   = new StateRegistry( DEFAULT_STATES )

describe( 'responsive overlay (#820)', () => {
	const responsive = { width: { tablet: '75%', mobile: '100%' }, 'style.spacing.padding': { md: '2rem' } }

	it( 'shows the Mobile value at Mobile and leaves All sizes untouched', () => {
		expect( buildOverlay( responsive, 'base', registry ) ).toEqual( {} )
		expect( buildOverlay( responsive, 'mobile', registry ) ).toEqual( { width: '100%' } )
	} )

	it( 'shows Tablet plus the legacy rules that match at the tablet preview width', () => {
		expect( buildOverlay( responsive, 'tablet', registry ) ).toEqual( {
			width: '75%',
			style: { spacing: { padding: '2rem' } },
		} )
	} )

	it( 'lets Mobile inherit from Tablet', () => {
		expect( buildOverlay( { width: { tablet: '75%' } }, 'mobile', registry ) ).toEqual( { width: '75%' } )
	} )
} )

describe( 'InspectorScopeChip copy (#820)', () => {
	afterEach( () => resetActiveBreakpoint() )

	it( 'reads "and down" for device breakpoints', () => {
		render( <InspectorScopeChip registry={ registry } /> )
		act( () => setActiveBreakpoint( 'mobile' ) )

		expect( screen.getByRole( 'status' ).textContent ).toContain( 'Editing at Mobile and down' )
	} )
} )

describe( 'emitters (#820)', () => {
	it( 'emits gradient-border device overrides as max-width, tablet before mobile', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ idle: 'red', states: {}, breakpoints: { mobile: 'green', tablet: 'blue' }, width: null, radius: null },
			states,
			registry,
		)

		expect( css ).toContain( '@media (max-width:767px){.scope::before{background:green}}' )
		expect( css.indexOf( 'max-width:1023px' ) ).toBeLessThan( css.indexOf( 'max-width:767px' ) )
	} )

	it( 'emits box-shadow device overrides in descending max-width order', () => {
		const layer = ( blur: string ): ResolvedShadowLayer => ( {
			offsetX: '0', offsetY: '0', blur, spread: '0', color: '#000', gradient: null, inset: false, preset: null,
		} )
		const css   = emitBoxShadowCss(
			'.scope',
			{ idle: layer( '1px' ), states: {}, breakpoints: { mobile: layer( '3px' ), tablet: layer( '2px' ) } },
			states,
			registry,
		)

		expect( css.indexOf( '@media (max-width:1023px)' ) ).toBeGreaterThan( -1 )
		expect( css.indexOf( '@media (max-width:1023px)' ) ).toBeLessThan( css.indexOf( '@media (max-width:767px)' ) )
	} )

	it( 'cascades position layers downward and previews Mobile from Tablet', () => {
		const attributes = {
			style:      { position: { value: 'relative' } },
			responsive: { 'style.position': { tablet: { value: 'sticky' } } },
		}
		const payload = resolvePosition( attributes )!

		const css = emitPositionCss( '.scope', payload, registry, mergedBreakpointLayers( payload, registry ) )

		expect( css ).toContain( '@media (max-width:1023px){.scope{position:sticky !important}}' )
		expect( css ).not.toContain( 'min-width' )
		expect( resolveAtBreakpoint( attributes, 'mobile', registry )?.value ).toBe( 'sticky' )
		expect( resolveAtBreakpoint( attributes, 'base', registry )?.value ).toBe( 'relative' )
	} )
} )
