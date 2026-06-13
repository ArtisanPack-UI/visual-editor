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

		expect( css ).toContain( '.scope{position:relative;border-color:transparent !important}' )
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
			{ ...baseline, idle: 'red' },
			states,
			breakpoints,
		)

		expect( css ).toContain( 'padding:1px' )
		expect( css ).toContain( 'border-radius:inherit' )
	} )

	it( 'emits explicit border-radius when supplied', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'red', radius: '8px' },
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
				idle:   'red',
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
			{ ...baseline, idle: 'red', states: { hover: 'blue' } },
			states,
			breakpoints,
		)

		expect( css ).toContain( '@media (hover: hover){' )
		expect( css ).toContain( '.scope:hover::before{background:blue}' )
		expect( css ).toContain( '.scope::before{transition:' )
	} )

	it( 'emits per-breakpoint overrides under @media (min-width)', () => {
		const css = emitGradientBorderCss(
			'.scope',
			{ ...baseline, idle: 'red', breakpoints: { md: 'blue' } },
			states,
			breakpoints,
		)

		expect( css ).toContain( '@media (min-width:768px){.scope::before{background:blue}}' )
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
} )
