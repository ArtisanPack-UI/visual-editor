import { describe, expect, it } from 'vitest'

import { referencedSlugs, resolveGradientBorder } from '../resolver'

describe( 'resolveGradientBorder', () => {
	it( 'returns null when no gradient configuration exists', () => {
		expect( resolveGradientBorder( null ) ).toBeNull()
		expect( resolveGradientBorder( undefined ) ).toBeNull()
		expect( resolveGradientBorder( {} ) ).toBeNull()
		expect(
			resolveGradientBorder( {
				style: { border: { width: '2px' } },
			} ),
		).toBeNull()
	} )

	it( 'expands a slug into var(--wp--preset--gradient--{slug})', () => {
		const resolved = resolveGradientBorder( {
			style: { border: { gradient: 'primary-glow' } },
		} )

		expect( resolved ).not.toBeNull()
		expect( resolved!.idle ).toBe( 'var(--wp--preset--gradient--primary-glow)' )
	} )

	it( 'passes raw CSS gradient values through unchanged', () => {
		const raw      = 'linear-gradient(135deg, #ff0000, #0000ff)'
		const resolved = resolveGradientBorder( {
			style: { border: { gradient: raw } },
		} )

		expect( resolved!.idle ).toBe( raw )
	} )

	it( 'collects per-state and per-breakpoint overrides from the standard bags', () => {
		const resolved = resolveGradientBorder( {
			style:      { border: { gradient: 'primary-glow' } },
			states:     {
				'style.border.gradient': {
					hover: 'accent-glow',
					focus: null,
				},
			},
			responsive: {
				'style.border.gradient': {
					md: 'vertical',
				},
			},
		} )

		expect( resolved!.states ).toEqual( {
			hover: 'var(--wp--preset--gradient--accent-glow)',
		} )
		expect( resolved!.breakpoints ).toEqual( {
			md: 'var(--wp--preset--gradient--vertical)',
		} )
	} )

	it( 'accepts the shorthand `border.gradient` path key with canonical winning on conflict', () => {
		const resolved = resolveGradientBorder( {
			states: {
				'border.gradient':       { hover: 'shorthand-loses' },
				'style.border.gradient': { hover: 'canonical-wins' },
			},
		} )

		expect( resolved!.states.hover ).toBe(
			'var(--wp--preset--gradient--canonical-wins)',
		)
	} )

	it( 'preserves border width and radius alongside the gradient', () => {
		const resolved = resolveGradientBorder( {
			style: {
				border: {
					gradient: 'primary-glow',
					width:    '4px',
					radius:   '12px',
				},
			},
		} )

		expect( resolved!.width ).toBe( '4px' )
		expect( resolved!.radius ).toBe( '12px' )
	} )
} )

describe( 'referencedSlugs', () => {
	it( 'pulls slugs from every cascade slot', () => {
		const slugs = referencedSlugs( {
			style:      { border: { gradient: 'primary-glow' } },
			states:     {
				'style.border.gradient': { hover: 'primary-glow-bright' },
			},
			responsive: {
				'style.border.gradient': { md: 'vertical' },
			},
		} )

		expect( slugs.sort() ).toEqual( [
			'primary-glow',
			'primary-glow-bright',
			'vertical',
		] )
	} )

	it( 'deduplicates slugs that appear in multiple cascade slots', () => {
		const slugs = referencedSlugs( {
			style:      { border: { gradient: 'primary-glow' } },
			states:     {
				'style.border.gradient': { hover: 'primary-glow' },
			},
		} )

		expect( slugs ).toEqual( [ 'primary-glow' ] )
	} )

	it( 'ignores raw CSS values — they cannot become stale', () => {
		const slugs = referencedSlugs( {
			style: {
				border: {
					gradient: 'radial-gradient(circle, #f00, #00f)',
				},
			},
		} )

		expect( slugs ).toEqual( [] )
	} )
} )
