/**
 * Extend-supports filter — verifies that `style.shadow` lands in
 * `artisanpackStates.attributes` + `artisanpackResponsive.attributes`
 * for blocks with border support, while explicit `false` opt-outs are
 * preserved.
 *
 * Note: this test imports the filter implementation indirectly by
 * invoking the registrar against a stubbed @wordpress/hooks. The
 * registrar itself is sentinel-guarded so we reset the global between
 * runs.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

// Stub @wordpress/hooks BEFORE importing the module under test so the
// registrar's `addFilter` call hits our spy instead of trying to load
// the real Gutenberg hooks runtime under jsdom.
let lastFilterCallback: ( ( settings: unknown ) => unknown ) | null = null

vi.mock( '@wordpress/hooks', () => ( {
	addFilter: ( _hook: string, _ns: string, cb: ( settings: unknown ) => unknown ) => {
		lastFilterCallback = cb
	},
} ) )

import { registerBoxShadowSupportsExtension } from '../extend-supports'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.box-shadow-supports.registered',
)

beforeEach( () => {
	lastFilterCallback = null
	delete ( globalThis as Record<symbol, unknown> )[ REGISTERED_KEY as unknown as symbol ]
} )

function applyFilter( settings: Record<string, unknown> ): Record<string, unknown> {
	registerBoxShadowSupportsExtension()
	expect( lastFilterCallback ).not.toBeNull()
	return lastFilterCallback!( settings ) as Record<string, unknown>
}

describe( 'registerBoxShadowSupportsExtension', () => {
	it( 'is a no-op for blocks without any border support', () => {
		const next = applyFilter( { supports: { color: { background: true } } } )
		expect( next ).toEqual( { supports: { color: { background: true } } } )
	} )

	it( 'injects style.shadow into the routing lists for blocks with __experimentalBorder', () => {
		const next = applyFilter( {
			supports: { __experimentalBorder: { width: true } },
		} )

		const supports = next.supports as Record<string, unknown>
		expect( ( supports.artisanpackStates as { attributes: string[] } ).attributes ).toContain( 'style.shadow' )
		expect( ( supports.artisanpackResponsive as { attributes: string[] } ).attributes ).toContain( 'style.shadow' )
	} )

	it( 'preserves an explicit `false` opt-out on either routing key', () => {
		const next = applyFilter( {
			supports: {
				__experimentalBorder:   { width: true },
				artisanpackStates:      false,
				artisanpackResponsive:  false,
			},
		} )

		const supports = next.supports as Record<string, unknown>
		expect( supports.artisanpackStates ).toBe( false )
		expect( supports.artisanpackResponsive ).toBe( false )
	} )

	it( 'preserves existing attribute lists and appends style.shadow', () => {
		const next = applyFilter( {
			supports: {
				__experimentalBorder: { width: true },
				artisanpackStates:    { attributes: [ 'border.gradient' ] },
			},
		} )

		const supports = next.supports as Record<string, unknown>
		const stateAttrs = ( supports.artisanpackStates as { attributes: string[] } ).attributes
		expect( stateAttrs ).toEqual( [ 'border.gradient', 'style.shadow' ] )
	} )

	it( 'strips the native WordPress `supports.shadow` so the two panels do not fight over `style.shadow`', () => {
		const next = applyFilter( {
			supports: {
				__experimentalBorder: { width: true },
				shadow:               true,
			},
		} )

		const supports = next.supports as Record<string, unknown>
		expect( supports.shadow ).toBe( false )
	} )

	it( 'is idempotent when style.shadow is already present', () => {
		const next = applyFilter( {
			supports: {
				__experimentalBorder: { width: true },
				artisanpackStates:    { attributes: [ 'style.shadow' ] },
			},
		} )

		const supports = next.supports as Record<string, unknown>
		const stateAttrs = ( supports.artisanpackStates as { attributes: string[] } ).attributes
		expect( stateAttrs ).toEqual( [ 'style.shadow' ] )
	} )
} )
