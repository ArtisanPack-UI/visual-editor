import { describe, expect, it } from 'vitest'

import { BreakpointRegistry, TAILWIND_V4_DEFAULTS, registryFromSnapshot } from '../registry'

describe( 'BreakpointRegistry', () => {
	it( 'defaults to Tailwind v4 mins when nothing else is passed', () => {
		const registry = new BreakpointRegistry()

		expect( registry.prefixes() ).toEqual( [ 'sm', 'md', 'lg', 'xl', '2xl' ] )
		expect( registry.get( 'md' ) ).toBe( 768 )
	} )

	it( 'sorts breakpoints ascending regardless of input order', () => {
		const registry = new BreakpointRegistry( [
			{ key: '2xl', minWidthPx: 1536 },
			{ key: 'sm', minWidthPx: 640 },
			{ key: 'md', minWidthPx: 768 },
		] )

		expect( registry.prefixes() ).toEqual( [ 'sm', 'md', '2xl' ] )
	} )

	it( 'returns 0 for base and lists it in keysWithBase()', () => {
		const registry = new BreakpointRegistry()

		expect( registry.get( 'base' ) ).toBe( 0 )
		expect( registry.keysWithBase()[ 0 ] ).toBe( 'base' )
		expect( registry.has( 'base' ) ).toBe( true )
	} )

	it( 'returns null for unknown breakpoints', () => {
		const registry = new BreakpointRegistry()

		expect( registry.get( 'made-up' ) ).toBeNull()
	} )

	it( 'rebuilds from a snapshot or falls back to defaults', () => {
		expect( registryFromSnapshot( undefined ).prefixes() ).toEqual( [ 'sm', 'md', 'lg', 'xl', '2xl' ] )

		const custom = registryFromSnapshot( {
			breakpoints: [
				{ key: 'sm', minWidthPx: 640 },
				{ key: 'huge', minWidthPx: 2000 },
			],
		} )

		expect( custom.prefixes() ).toEqual( [ 'sm', 'huge' ] )
		expect( custom.get( 'huge' ) ).toBe( 2000 )
	} )

	it( 'exports the Tailwind v4 default list as a constant', () => {
		expect( TAILWIND_V4_DEFAULTS.map( ( bp ) => bp.key ) ).toEqual( [ 'sm', 'md', 'lg', 'xl', '2xl' ] )
	} )
} )
