import { describe, expect, it } from 'vitest'

import { normalizeShadowSubtree, referencedSlugs, resolveBoxShadow } from '../resolver'

describe( 'resolveBoxShadow', () => {
	it( 'returns null for missing, null, or empty attributes', () => {
		expect( resolveBoxShadow( null ) ).toBeNull()
		expect( resolveBoxShadow( undefined ) ).toBeNull()
		expect( resolveBoxShadow( {} ) ).toBeNull()
		expect( resolveBoxShadow( { style: { shadow: {} } } ) ).toBeNull()
	} )

	it( 'returns a structured idle layer when fields are set', () => {
		const resolved = resolveBoxShadow( {
			style: {
				shadow: {
					offsetX: '2px',
					offsetY: '4px',
					blur:    '8px',
					spread:  '0',
					color:   '#000',
				},
			},
		} )

		expect( resolved ).not.toBeNull()
		expect( resolved!.idle ).toEqual( {
			offsetX:  '2px',
			offsetY:  '4px',
			blur:     '8px',
			spread:   '0',
			color:    '#000',
			gradient: null,
			inset:    false,
			preset:   null,
			value:    null,
		} )
	} )

	it( 'expands a gradient slug into var(--wp--preset--gradient--{slug})', () => {
		const resolved = resolveBoxShadow( {
			style: { shadow: { offsetX: '0', gradient: 'primary-glow' } },
		} )

		expect( resolved!.idle!.gradient ).toBe( 'var(--wp--preset--gradient--primary-glow)' )
	} )

	it( 'passes raw CSS gradient values through unchanged', () => {
		const raw      = 'linear-gradient(135deg, #ff0000, #0000ff)'
		const resolved = resolveBoxShadow( {
			style: { shadow: { gradient: raw } },
		} )

		expect( resolved!.idle!.gradient ).toBe( raw )
	} )

	it( 'captures a preset slug onto the layer', () => {
		const resolved = resolveBoxShadow( {
			style: { shadow: { preset: 'shadow-md' } },
		} )

		expect( resolved!.idle!.preset ).toBe( 'shadow-md' )
	} )

	it( 'honours the inset flag', () => {
		const resolved = resolveBoxShadow( {
			style: { shadow: { offsetX: '0', inset: true } },
		} )

		expect( resolved!.idle!.inset ).toBe( true )
	} )

	it( 'collects per-state and per-breakpoint overrides from the standard bags', () => {
		const resolved = resolveBoxShadow( {
			style:      { shadow: { offsetX: '2px', blur: '4px', color: '#000' } },
			states:     {
				'style.shadow': {
					hover: { offsetX: '4px', blur: '8px', color: '#111' },
					focus: null,
				},
			},
			responsive: {
				'style.shadow': {
					md: { offsetX: '6px', blur: '12px', color: '#000' },
				},
			},
		} )

		expect( resolved!.states.hover.blur ).toBe( '8px' )
		expect( resolved!.states.focus ).toBeUndefined()
		expect( resolved!.breakpoints.md.blur ).toBe( '12px' )
	} )

	it( 'accepts the shorthand `shadow` path key with canonical winning on conflict', () => {
		const resolved = resolveBoxShadow( {
			states: {
				'shadow':       { hover: { blur: '99px' } },
				'style.shadow': { hover: { blur: '8px' } },
			},
		} )

		expect( resolved!.states.hover.blur ).toBe( '8px' )
	} )
} )

describe( 'referencedSlugs', () => {
	it( 'pulls shadow + gradient slugs from every cascade slot', () => {
		const slugs = referencedSlugs( {
			style:      { shadow: { preset: 'shadow-md', gradient: 'brand-glow' } },
			states:     {
				'style.shadow': { hover: { preset: 'shadow-elevated' } },
			},
			responsive: {
				'style.shadow': { md: { gradient: 'vertical' } },
			},
		} )

		expect( slugs.shadows.sort() ).toEqual( [ 'shadow-elevated', 'shadow-md' ] )
		expect( slugs.gradients.sort() ).toEqual( [ 'brand-glow', 'vertical' ] )
	} )

	it( 'deduplicates slugs that appear in multiple cascade slots', () => {
		const slugs = referencedSlugs( {
			style:  { shadow: { preset: 'shadow-md' } },
			states: { 'style.shadow': { hover: { preset: 'shadow-md' } } },
		} )

		expect( slugs.shadows ).toEqual( [ 'shadow-md' ] )
	} )

	it( 'ignores raw CSS gradient values — they cannot become stale', () => {
		const slugs = referencedSlugs( {
			style: { shadow: { gradient: 'linear-gradient(#f00, #00f)' } },
		} )

		expect( slugs.gradients ).toEqual( [] )
	} )
} )

describe( 'string shadow values (#900)', () => {
	it( 'reads the var:preset|shadow|{slug} string WordPress saves as a preset', () => {
		const resolved = resolveBoxShadow( { style: { shadow: 'var:preset|shadow|natural' } } )

		expect( resolved!.idle!.preset ).toBe( 'natural' )
		expect( resolved!.idle!.value ).toBeNull()
	} )

	it( 'reads a var(--wp--preset--shadow--{slug}) string as a preset', () => {
		expect( normalizeShadowSubtree( 'var(--wp--preset--shadow--deep)' ) ).toEqual( { preset: 'deep' } )
	} )

	it( 'reads any other printable string as a raw box-shadow value', () => {
		const resolved = resolveBoxShadow( { style: { shadow: ' 6px 6px 9px rgba(0, 0, 0, 0.2) ' } } )

		expect( resolved!.idle!.preset ).toBeNull()
		expect( resolved!.idle!.value ).toBe( '6px 6px 9px rgba(0, 0, 0, 0.2)' )
	} )

	it.each( [
		[ 'empty', '   ' ],
		[ 'rule breakout', '1px 1px red; } body { color: red' ],
		[ 'unclosed paren', '1px 1px rgba(0, 0, 0' ],
		[ 'markup', '</style><script>' ],
	] )( 'drops a string that is unsafe to print or empty (%s)', ( _label, shadow ) => {
		expect( resolveBoxShadow( { style: { shadow } } ) ).toBeNull()
	} )

	it( 'reads string values in the states and responsive bags', () => {
		const resolved = resolveBoxShadow( {
			states:     { 'style.shadow': { hover: 'var:preset|shadow|sharp' } },
			responsive: { 'style.shadow': { md: '0 0 4px #000' } },
		} )

		expect( resolved!.states.hover.preset ).toBe( 'sharp' )
		expect( resolved!.breakpoints.md.value ).toBe( '0 0 4px #000' )
	} )

	it( 'collects preset slugs referenced by string values', () => {
		expect( referencedSlugs( {
			style:  { shadow: 'var:preset|shadow|natural' },
			states: { 'style.shadow': { hover: 'var:preset|shadow|crisp' } },
		} ).shadows ).toEqual( [ 'natural', 'crisp' ] )
	} )

	it( 'passes structured subtrees through and ignores non-strings', () => {
		const subtree = { blur: '4px' }

		expect( normalizeShadowSubtree( subtree ) ).toBe( subtree )
		expect( normalizeShadowSubtree( 42 ) ).toBeNull()
		expect( normalizeShadowSubtree( [ 'x' ] ) ).toBeNull()
	} )
} )
