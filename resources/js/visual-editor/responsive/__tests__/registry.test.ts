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

	// #617 — device labels + preview widths
	describe( '#617 preview width + label extensions', () => {
		it( 'ships Mobile/Tablet/Desktop labels and device-sized preview widths by default', () => {
			const registry = new BreakpointRegistry()

			expect( registry.label( 'sm' ) ).toBe( 'Mobile' )
			expect( registry.label( 'md' ) ).toBe( 'Tablet' )
			expect( registry.label( 'lg' ) ).toBe( 'Desktop' )

			expect( registry.previewWidth( 'sm' ) ).toBe( 375 )
			expect( registry.previewWidth( 'md' ) ).toBe( 768 )
			expect( registry.previewWidth( 'lg' ) ).toBe( 1440 )
		} )

		it( 'falls back to minWidthPx when a snapshot entry omits previewWidthPx', () => {
			const registry = new BreakpointRegistry( [
				{ key: 'zoom', minWidthPx: 900 },
			] )

			expect( registry.previewWidth( 'zoom' ) ).toBe( 900 )
			expect( registry.label( 'zoom' ) ).toBe( 'zoom' )
		} )

		it( 'returns 0 previewWidth for base and null for unknown keys', () => {
			const registry = new BreakpointRegistry()

			expect( registry.previewWidth( 'base' ) ).toBe( 0 )
			expect( registry.previewWidth( 'nope' ) ).toBeNull()
		} )

		it( 'honours an explicit preview width on hydration', () => {
			const registry = registryFromSnapshot( {
				breakpoints: [
					{ key: 'sm', minWidthPx: 640, previewWidthPx: 390, label: 'iPhone' },
				],
			} )

			expect( registry.previewWidth( 'sm' ) ).toBe( 390 )
			expect( registry.label( 'sm' ) ).toBe( 'iPhone' )
		} )

		it( 'treats a non-positive previewWidthPx as a fallback to minWidthPx', () => {
			// A pre-#617 snapshot could serialise with `previewWidthPx: 0`
			// if the server-side registry accidentally emits it; the JS
			// registry should still resolve to something sensible rather
			// than pinning the canvas to 0px.
			const registry = new BreakpointRegistry( [
				{ key: 'sm', minWidthPx: 640, previewWidthPx: 0 },
			] )

			expect( registry.previewWidth( 'sm' ) ).toBe( 640 )
		} )
	} )
} )
