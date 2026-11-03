import { describe, expect, it } from 'vitest'

import { TAILWIND_V4_DEFAULTS, BreakpointRegistry } from '../../responsive/registry'
import { DEFAULT_STATES, StateRegistry } from '../../states/registry'
import { emitGradientBorderCss } from '../emitter'
import type { ResolvedGradientBorder } from '../types'

const states      = new StateRegistry( DEFAULT_STATES )
const breakpoints = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

const baseline: ResolvedGradientBorder = {
	idle:        null,
	states:      {},
	breakpoints: {},
	width:       null,
	radius:      null,
}

describe( 'emitGradientBorderCss', () => {
	it( 'returns empty when scope is blank or payload is null', () => {
		expect( emitGradientBorderCss( '', baseline, states, breakpoints ) ).toBe( '' )
		expect( emitGradientBorderCss( '.scope', null, states, breakpoints ) ).toBe( '' )
	} )

	it( 'returns empty when the payload has no values anywhere', () => {
		expect( emitGradientBorderCss( '.scope', baseline, states, breakpoints ) ).toBe(
			'',
		)
	} )

	it( 'emits the wrapper + mask ::before for an idle gradient', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'linear-gradient(135deg, #ff0000, #0000ff)', width: '2px' },
			states,
			breakpoints,
		)

		expect( css ).toContain( '.scope{position:relative;border-color:transparent !important;overflow-clip-margin:border-box}' )
		expect( css ).toContain( '.scope::before{content:""' )
		expect( css ).toContain( 'padding:2px' )
		expect( css ).toContain( 'background:linear-gradient(135deg, #ff0000, #0000ff)' )
		expect( css ).toContain( 'mask-composite:exclude' )
		expect( css ).toContain( '-webkit-mask-composite:xor' )
		expect( css ).toContain( 'pointer-events:none' )
	} )

	it( 'defaults width to 1px and radius to inherit when absent', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)' },
			states,
			breakpoints,
		)

		expect( css ).toContain( 'padding:1px' )
		expect( css ).toContain( 'border-radius:inherit' )
	} )

	it( 'emits explicit border-radius when supplied', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)', radius: '8px' },
			states,
			breakpoints,
		)

		expect( css ).toContain( 'border-radius:8px' )
	} )

	it( 'expands per-corner radius objects to four declarations', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{
				...baseline,
				idle:   'var(--r)',
				radius: {
					topLeft:     '4px',
					topRight:    '8px',
					bottomLeft:  '12px',
					bottomRight: '16px',
				},
			},
			states,
			breakpoints,
		)

		expect( css ).toContain( 'border-top-left-radius:4px' )
		expect( css ).toContain( 'border-top-right-radius:8px' )
		expect( css ).toContain( 'border-bottom-left-radius:12px' )
		expect( css ).toContain( 'border-bottom-right-radius:16px' )
	} )

	it( 'wraps hover under @media (hover: hover) and emits the override', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)', states: { hover: 'var(--b)' } },
			states,
			breakpoints,
		)

		expect( css ).toContain( '@media (hover: hover){' )
		expect( css ).toContain( '.scope:hover::before{background:var(--b)}' )
		expect( css ).toContain( '.scope::before{transition:' )
	} )

	it( 'emits per-breakpoint overrides under @media (min-width)', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)', breakpoints: { md: 'var(--b)' } },
			states,
			breakpoints,
		)

		expect( css ).toContain( '@media (min-width:768px){.scope::before{background:var(--b)}}' )
	} )

	it( 'sanitises gradient and width values to drop context-breaking characters', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{
				...baseline,
				idle:  'linear-gradient(<script>red;</script>blue)',
				width: '2px"; }</style>',
			},
			states,
			breakpoints,
		)

		// `<`, `>`, `}`, `"`, `;` (in user payload) all stripped from
		// the unsafe slots. `content:""` legitimately has the `"`
		// character so we can't assert on a global absence — check the
		// specific corrupted constructions instead.
		expect( css ).not.toContain( '<script>' )
		expect( css ).not.toContain( '</style>' )
		expect( css ).not.toContain( 'padding:2px";' )
		expect( css ).not.toContain( '}</' )
	} )

	it( 'lets a Cover stop clipping where overflow-clip-margin is unsupported (#912)', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)', width: '3px', radius: '12px' },
			states,
			breakpoints,
		)

		expect( css ).toContain( '@supports not (overflow-clip-margin:border-box){.scope.wp-block-cover{overflow:visible}' )
		expect( css ).toContain(
			'.scope.wp-block-cover>:is(.wp-block-cover__background,.wp-block-cover__gradient-background,'
			+ '.wp-block-cover__image-background,.wp-block-cover__video-background)'
			+ '{border-radius:max(0px, calc(12px - 3px));}}',
		)
	} )

	it( 'insets each per-corner radius by the ring width in the Cover fallback', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)', width: '3px', radius: { topLeft: '12px', topRight: '1rem' } },
			states,
			breakpoints,
		)

		expect( css ).toContain( 'border-top-left-radius:max(0px, calc(12px - 3px));border-top-right-radius:max(0px, calc(1rem - 3px));}}' )
	} )

	it( 'leaves Cover background layers square in the fallback when no radius is set', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'var(--r)', width: '3px' },
			states,
			breakpoints,
		)

		expect( css ).toContain( '@supports not (overflow-clip-margin:border-box){.scope.wp-block-cover{overflow:visible}}' )
		expect( css ).not.toContain( 'wp-block-cover__background' )
	} )
} )

describe( 'emitGradientBorderCss value guard (#1083)', () => {
	const gradient = 'linear-gradient(red, blue)'

	it.each( [
		[ 'width', '/*' ],
		[ 'width', 'calc(2px' ],
		[ 'radius', '4px */' ],
		[ 'radius', { topLeft: '4px//', topRight: '2px' } ],
		[ 'width', '/"*' ],
	] as const )( 'treats a %s of %j as unset', ( field, value ) => {
		const css = emitGradientBorderCss( '.scope', { ...baseline, idle: gradient, [ field ]: value }, states, breakpoints )

		expect( css ).not.toContain( '/*' )
		expect( css ).not.toContain( '*/' )
		expect( css ).not.toContain( '//' )
		expect( css ).toContain( `background:${ gradient }` )
	} )

	it( 'falls back to the default width for a rejected width', () => {
		const css = emitGradientBorderCss( '.scope', { ...baseline, idle: gradient, width: '/*' }, states, breakpoints )

		expect( css ).toContain( 'inset:calc(-1 * 1px);padding:1px;' )
	} )

	it( 'keeps the safe corners of a per-corner radius', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: gradient, radius: { topLeft: '/*', topRight: '2px' } },
			states,
			breakpoints,
		)

		expect( css ).toContain( 'border-top-right-radius:2px' )
		expect( css ).not.toContain( 'border-top-left-radius' )
	} )

	it( 'drops gradients that open a comment', () => {
		expect( emitGradientBorderCss( '.scope', { ...baseline, idle: `${ gradient } /*` }, states, breakpoints ) ).toBe( '' )

		const css = emitGradientBorderCss(
			'.scope',
			{
				...baseline,
				idle:        gradient,
				states:      { hover: 'linear-gradient(red, blue /*' },
				breakpoints: { md: `${ gradient } */` },
			},
			states,
			breakpoints,
		)

		expect( css ).not.toContain( '/*' )
		expect( css ).not.toContain( '*/' )
		expect( css ).not.toContain( ':hover' )
	} )

	it.each( [
		'url(http:evil.example/t.png)',
		'image-set(t.png 1x)',
		'linear-gradient(red,red) padding-box,url(t.png)',
	] )( 'drops the gradient %s, which could fetch a resource', ( bad ) => {
		expect( emitGradientBorderCss( '.scope', { ...baseline, idle: bad }, states, breakpoints ) ).toBe( '' )

		const css = emitGradientBorderCss( '.scope', { ...baseline, idle: gradient, states: { hover: bad } }, states, breakpoints )

		expect( css ).not.toContain( 'url(' )
		expect( css ).not.toContain( 'image-set(' )
		expect( css ).not.toContain( ':hover' )
	} )
} )
