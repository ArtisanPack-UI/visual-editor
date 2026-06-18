import { describe, expect, it } from 'vitest'

import { TAILWIND_V4_DEFAULTS, BreakpointRegistry } from '../../responsive/registry'
import { DEFAULT_STATES, StateRegistry } from '../../states/registry'
import { emitBoxShadowCss } from '../emitter'
import type { ResolvedBoxShadow, ResolvedShadowLayer } from '../types'

const states      = new StateRegistry( DEFAULT_STATES )
const breakpoints = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

function layer( overrides: Partial<ResolvedShadowLayer> = {} ): ResolvedShadowLayer {
	return {
		offsetX:  '0',
		offsetY:  '0',
		blur:     '0',
		spread:   '0',
		color:    null,
		gradient: null,
		inset:    false,
		preset:   null,
		...overrides,
	}
}

const baseline: ResolvedBoxShadow = {
	idle:        null,
	states:      {},
	breakpoints: {},
}

describe( 'emitBoxShadowCss', () => {
	it( 'returns empty when scope is blank or payload is null', () => {
		expect( emitBoxShadowCss( '', baseline, states, breakpoints ) ).toBe( '' )
		expect( emitBoxShadowCss( '.scope', null, states, breakpoints ) ).toBe( '' )
	} )

	it( 'returns empty when the payload has no values anywhere', () => {
		expect( emitBoxShadowCss( '.scope', baseline, states, breakpoints ) ).toBe( '' )
	} )

	it( 'emits a stock box-shadow for a solid outer layer', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { offsetX: '2px', offsetY: '4px', blur: '8px', color: '#000' } ) },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{box-shadow:2px 4px 8px 0 #000}' )
		expect( css ).not.toContain( '::before' )
		expect( css ).not.toContain( 'position:relative' )
	} )

	it( 'emits inset prefix for a solid inset layer', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { blur: '6px', color: '#333', inset: true } ) },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{box-shadow:inset 0 0 6px 0 #333}' )
	} )

	it( 'emits var() for a preset layer', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { preset: 'shadow-md' } ) },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{box-shadow:var(--wp--preset--shadow--shadow-md)}' )
	} )

	it( 'appends inset suffix on preset layers when inset is true', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { preset: 'shadow-lg', inset: true } ) },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{box-shadow:var(--wp--preset--shadow--shadow-lg) inset}' )
	} )

	it( 'emits the ::before pseudo for an outer gradient layer with position relative on the wrapper', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( {
				offsetX:  '4px',
				offsetY:  '6px',
				blur:     '12px',
				spread:   '2px',
				gradient: 'linear-gradient(135deg, #ff0000, #0000ff)',
			} ) },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{position:relative;isolation:isolate}' )
		expect( css ).toContain( '.scope::before{' )
		expect( css ).toContain( 'background:linear-gradient(135deg, #ff0000, #0000ff)' )
		expect( css ).toContain( 'filter:blur(12px)' )
		expect( css ).toContain( 'transform:translate(4px,6px)' )
		expect( css ).toContain( 'z-index:-1' )
	} )

	it( 'emits the ::after pseudo with mask-composite for an inset gradient layer', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( {
				offsetX:  '4px',
				offsetY:  '6px',
				blur:     '8px',
				spread:   '4px',
				gradient: 'linear-gradient(180deg, #fff, #000)',
				inset:    true,
			} ) },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{position:relative;isolation:isolate}' )
		expect( css ).toContain( '.scope::after{' )
		expect( css ).toContain( 'mask-composite:exclude' )
		expect( css ).toContain( 'transform:translate(calc(-1 * 4px),calc(-1 * 6px))' )
		expect( css ).toContain( 'padding:4px' )
	} )

	it( 'wraps hover state rules in @media (hover: hover)', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{
				...baseline,
				idle:   layer( { blur: '4px', color: '#000' } ),
				states: { hover: layer( { blur: '12px', color: '#111' } ) },
			},
			states,
			breakpoints,
		)

		expect( css ).toContain( '@media (hover: hover){' )
		expect( css ).toContain( 'transition:' )
	} )

	it( 'wraps breakpoint rules in a min-width media query', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{
				...baseline,
				idle:        layer( { blur: '4px', color: '#000' } ),
				breakpoints: { md: layer( { blur: '16px', color: '#000' } ) },
			},
			states,
			breakpoints,
		)

		expect( css ).toMatch( /@media \(min-width:\d+px\)\{\.scope\{box-shadow:0 0 16px 0 #000\}\}/ )
	} )
} )
