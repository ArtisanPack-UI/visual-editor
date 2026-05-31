import { describe, expect, it } from 'vitest'

import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../registry'
import { distinctOverrides, isResponsiveAttribute, resolveResponsiveValue } from '../resolver'

const registry = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

describe( 'resolveResponsiveValue', () => {
	it( 'returns scalars unchanged', () => {
		expect( resolveResponsiveValue( 4, 'md', registry ) ).toBe( 4 )
		expect( resolveResponsiveValue( 'left', 'md', registry ) ).toBe( 'left' )
	} )

	it( 'returns null for null/undefined input', () => {
		expect( resolveResponsiveValue( null, 'md', registry ) ).toBeNull()
		expect( resolveResponsiveValue( undefined, 'md', registry ) ).toBeNull()
	} )

	it( 'cascades smaller breakpoint values through null slots', () => {
		const attribute = { base: 4, sm: 1, md: null, lg: null }

		expect( resolveResponsiveValue( attribute, 'sm', registry ) ).toBe( 1 )
		expect( resolveResponsiveValue( attribute, 'md', registry ) ).toBe( 1 )
		expect( resolveResponsiveValue( attribute, 'lg', registry ) ).toBe( 1 )
	} )

	it( 'returns the largest defined override at or below the active breakpoint', () => {
		const attribute = { base: 3, sm: 1, md: 2 }

		expect( resolveResponsiveValue( attribute, 'sm', registry ) ).toBe( 1 )
		expect( resolveResponsiveValue( attribute, 'md', registry ) ).toBe( 2 )
		expect( resolveResponsiveValue( attribute, 'lg', registry ) ).toBe( 2 )
		expect( resolveResponsiveValue( attribute, 'base', registry ) ).toBe( 3 )
	} )

	it( 'returns null when no slot at or below the active breakpoint is defined', () => {
		const attribute = { md: 5 }

		expect( resolveResponsiveValue( attribute, 'sm', registry ) ).toBeNull()
	} )

	it( 'falls back to base when active breakpoint is unknown', () => {
		const attribute = { base: 7, md: 9 }

		expect( resolveResponsiveValue( attribute, 'made-up', registry ) ).toBe( 7 )
	} )
} )

describe( 'isResponsiveAttribute', () => {
	it( 'recognises base-keyed and registry-keyed objects', () => {
		expect( isResponsiveAttribute( { base: 1 }, registry ) ).toBe( true )
		expect( isResponsiveAttribute( { md: 1 }, registry ) ).toBe( true )
		expect( isResponsiveAttribute( { unrelated: 1 }, registry ) ).toBe( false )
		expect( isResponsiveAttribute( [ 1, 2, 3 ], registry ) ).toBe( false )
		expect( isResponsiveAttribute( 'string', registry ) ).toBe( false )
	} )
} )

describe( 'distinctOverrides', () => {
	it( 'compresses redundant inherited values', () => {
		expect( distinctOverrides( { base: 4, sm: 4, md: 6, lg: 6 }, registry ) ).toEqual( {
			base: 4,
			md:   6,
		} )
	} )
} )
