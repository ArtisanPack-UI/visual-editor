import { describe, expect, it } from 'vitest'

import { TAILWIND_V4_DEFAULTS, BreakpointRegistry } from '../../responsive/registry'
import { DEFAULT_STATES, StateRegistry } from '../../states/registry'
import { emitBoxShadowCss, shadowScopeFor } from '../emitter'
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
		value:    null,
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

describe( 'emitBoxShadowCss raw values (#900)', () => {
	it( 'emits a raw value as the box-shadow declaration', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { value: '6px 6px 9px rgba(0, 0, 0, 0.2)' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '.scope{box-shadow:6px 6px 9px rgba(0, 0, 0, 0.2)}' )
	} )

	it( 'lets a preset win over a raw value', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { preset: 'natural', value: '1px 1px red' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '.scope{box-shadow:var(--wp--preset--shadow--natural)}' )
	} )

	it( 'drops a raw value whose parentheses do not balance once sanitised', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { value: '1px 1px rgba(0, 0, 0' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '' )
	} )
} )

describe( 'shadowScopeFor (#1052)', () => {
	const solid = { preset: 'natural', gradient: null } as unknown as ResolvedShadowLayer
	const gradient = { preset: null, value: null, gradient: 'linear-gradient(red, blue)' } as unknown as ResolvedShadowLayer

	it.each( [ 'core/image', 'artisanpack/image' ] )( 'targets the img of %s', ( name ) => {
		expect( shadowScopeFor( name, '.ve-bs-a', { idle: solid, states: {}, breakpoints: {} } ) ).toBe( '.ve-bs-a img' )
	} )

	it( 'keeps other blocks on the wrapper', () => {
		expect( shadowScopeFor( 'core/group', '.ve-bs-a', { idle: solid, states: {}, breakpoints: {} } ) ).toBe( '.ve-bs-a' )
	} )

	it.each( [
		[ 'idle', { idle: gradient, states: {}, breakpoints: {} } ],
		[ 'state', { idle: solid, states: { hover: gradient }, breakpoints: {} } ],
		[ 'breakpoint', { idle: solid, states: {}, breakpoints: { md: gradient } } ],
	] )( 'keeps an image on the wrapper for a gradient %s layer', ( _level, payload ) => {
		expect( shadowScopeFor( 'core/image', '.ve-bs-a', payload ) ).toBe( '.ve-bs-a' )
	} )

	it( 'puts state rules on the img', () => {
		const css = emitBoxShadowCss(
			shadowScopeFor( 'core/image', '.ve-bs-a', { idle: solid, states: { hover: solid }, breakpoints: {} } ),
			{ idle: solid, states: { hover: solid }, breakpoints: {} },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.ve-bs-a img{box-shadow:' )
		expect( css ).toContain( '.ve-bs-a img:hover' )
	} )
} )

describe( 'emitBoxShadowCss structured field guard (#1083)', () => {
	it.each( [
		[ 'offsetX', '/*' ],
		[ 'offsetY', '*/' ],
		[ 'blur', '1px//' ],
		[ 'spread', 'calc(1px' ],
		[ 'color', 'red /*' ],
		[ 'offsetX', '/"*' ],
	] as const )( 'treats a %s of %s as unset', ( field, value ) => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { offsetX: '2px', blur: '4px', color: '#000', [ field ]: value } ) },
			states,
			breakpoints,
		)

		expect( css ).not.toContain( '/*' )
		expect( css ).not.toContain( '*/' )
		expect( css ).not.toContain( '//' )
		expect( css.startsWith( '.scope{box-shadow:' ) ).toBe( true )
	} )

	it( 'falls back to the defaults for rejected lengths and color', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { offsetX: '/*', offsetY: '2px', blur: '4px', color: '/*' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '.scope{box-shadow:0px 2px 4px 0 currentColor}' )
	} )

	it( 'drops a layer when every structured field is rejected', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { offsetX: '/*', offsetY: '', blur: '', spread: '', color: '*/' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '' )
	} )

	it.each( [
		'linear-gradient(red, blue) /*',
		'url(http:evil.example/t.png)',
		'url(t.png)',
		'image-set(http:evil.example/t.png 1x)',
		'linear-gradient(red, blue),url(t.png)',
		'linear-gradient(url(t.png), blue)',
	] )( 'drops a layer whose gradient %s is rejected, rather than painting it solid', ( gradient ) => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { blur: '4px', gradient } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '' )
	} )

	it( 'keeps a preset when its gradient is rejected', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { preset: 'natural', gradient: 'url(t.png)' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '.scope{box-shadow:var(--wp--preset--shadow--natural)}' )
	} )

	it.each( [
		'linear-gradient(135deg, rgba(0, 0, 0, 0.5), var(--x))',
		'var(--wp--preset--gradient--sunset)',
	] )( 'keeps the gradient %s', ( gradient ) => {
		const css = emitBoxShadowCss( '.scope', { ...baseline, idle: layer( { gradient } ) }, states, breakpoints )

		expect( css ).toContain( `background:${ gradient };` )
	} )

	it( 'treats a blank preset as unset, as the server does', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, states: { hover: layer( { preset: ' ', offsetX: '', offsetY: '', blur: '', spread: '' } ) } },
			states,
			breakpoints,
		)

		expect( css ).toBe( '' )
	} )

	it( 'drops a raw value that opens a comment', () => {
		const css = emitBoxShadowCss(
			'.scope',
			{ ...baseline, idle: layer( { value: '1px 1px red /*' } ) },
			states,
			breakpoints,
		)

		expect( css ).toBe( '' )
	} )
} )

describe( 'emitBoxShadowCss preset slugs (#1084)', () => {
	it.each( [
		[ 'soft_glow', 'soft-glow' ],
		[ 'softGlow', 'soft-glow' ],
		[ 'natural', 'natural' ],
	] )( 'normalizes %s to %s', ( preset, expected ) => {
		const css = emitBoxShadowCss( '.scope', { ...baseline, idle: layer( { preset } ) }, states, breakpoints )

		expect( css ).toBe( `.scope{box-shadow:var(--wp--preset--shadow--${ expected })}` )
	} )
} )
