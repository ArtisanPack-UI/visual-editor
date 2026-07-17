/**
 * `extend-supports` tests for the position feature (#641).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/hooks', () => ( {
	addFilter: vi.fn(),
} ) )

import { addFilter } from '@wordpress/hooks'

import {
	positionEnabled,
	registerPositionSupportsExtension,
} from '../extend-supports'

describe( 'positionEnabled', () => {
	it( 'returns true for supports.position === true', () => {
		expect( positionEnabled( { position: true } ) ).toBe( true )
	} )

	it( 'returns true for an object at supports.position (Gutenberg native shape)', () => {
		expect( positionEnabled( { position: { sticky: true } } ) ).toBe( true )
	} )

	it( 'returns false for undefined, false, or missing supports', () => {
		expect( positionEnabled( undefined ) ).toBe( false )
		expect( positionEnabled( {} ) ).toBe( false )
		expect( positionEnabled( { position: false } ) ).toBe( false )
	} )
} )

describe( 'registerPositionSupportsExtension', () => {
	it( 'attaches the blocks.registerBlockType filter that routes style.position', () => {
		vi.mocked( addFilter ).mockClear()
		// Clear the sentinel so multiple test runs re-register cleanly.
		delete ( globalThis as unknown as Record<symbol, unknown> )[
			Symbol.for( 'artisanpack-ui.visual-editor.position-supports.registered' )
		]

		registerPositionSupportsExtension()

		expect( addFilter ).toHaveBeenCalledOnce()
		const [ hook, ns, cb ] = vi.mocked( addFilter ).mock.calls[ 0 ]
		expect( hook ).toBe( 'blocks.registerBlockType' )
		expect( ns ).toBe( 'artisanpack-ui/visual-editor/position-supports' )

		const filtered = ( cb as ( s: unknown ) => Record<string, unknown> )( {
			supports: { position: true },
		} )
		const supports = filtered.supports as Record<string, unknown>
		expect( supports.artisanpackResponsive ).toEqual( {
			attributes: [ 'style.position' ],
		} )
	} )

	it( 'skips blocks that do not opt into position support', () => {
		vi.mocked( addFilter ).mockClear()
		delete ( globalThis as unknown as Record<symbol, unknown> )[
			Symbol.for( 'artisanpack-ui.visual-editor.position-supports.registered' )
		]

		registerPositionSupportsExtension()
		const cb = vi.mocked( addFilter ).mock.calls[ 0 ][ 2 ] as ( s: unknown ) => unknown

		const settings = { supports: { color: {} } }
		expect( cb( settings ) ).toBe( settings )
	} )

	it( 'preserves an explicit artisanpackResponsive: false opt-out', () => {
		vi.mocked( addFilter ).mockClear()
		delete ( globalThis as unknown as Record<symbol, unknown> )[
			Symbol.for( 'artisanpack-ui.visual-editor.position-supports.registered' )
		]

		registerPositionSupportsExtension()
		const cb = vi.mocked( addFilter ).mock.calls[ 0 ][ 2 ] as ( s: unknown ) => Record<string, unknown>

		const filtered = cb( {
			supports: {
				position: true,
				artisanpackResponsive: false,
			},
		} )
		const supports = filtered.supports as Record<string, unknown>
		expect( supports.artisanpackResponsive ).toBe( false )
	} )

	it( 'appends style.position to an existing attributes list without duplicating', () => {
		vi.mocked( addFilter ).mockClear()
		delete ( globalThis as unknown as Record<symbol, unknown> )[
			Symbol.for( 'artisanpack-ui.visual-editor.position-supports.registered' )
		]

		registerPositionSupportsExtension()
		const cb = vi.mocked( addFilter ).mock.calls[ 0 ][ 2 ] as ( s: unknown ) => Record<string, unknown>

		const filtered = cb( {
			supports: {
				position: true,
				artisanpackResponsive: { attributes: [ 'style.spacing' ] },
			},
		} )
		const supports = filtered.supports as Record<string, unknown>
		expect( supports.artisanpackResponsive ).toEqual( {
			attributes: [ 'style.spacing', 'style.position' ],
		} )

		// Second application must not duplicate.
		const twice = cb( filtered )
		expect(
			( ( twice as Record<string, unknown> ).supports as Record<string, unknown> ).artisanpackResponsive,
		).toEqual( {
			attributes: [ 'style.spacing', 'style.position' ],
		} )
	} )
} )
