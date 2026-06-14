import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const filters = new Map<string, Array<{ namespace: string; callback: ( settings: unknown ) => unknown }>>()

vi.mock( '@wordpress/hooks', () => ( {
	addFilter: (
		hook: string,
		namespace: string,
		callback: ( settings: unknown ) => unknown,
	) => {
		const list = filters.get( hook ) ?? []
		list.push( { namespace, callback } )
		filters.set( hook, list )
	},
} ) )

import { registerBindingsAttribute } from '../register-attribute'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.bindings-attribute.registered',
)

function runFilter( settings: Record<string, unknown> ): Record<string, unknown> {
	const list = filters.get( 'blocks.registerBlockType' ) ?? []
	return list.reduce(
		( acc, { callback } ) => callback( acc ) as Record<string, unknown>,
		settings,
	)
}

beforeEach( () => {
	filters.clear()
	delete ( globalThis as Record<symbol, unknown> )[ REGISTERED_KEY ]
} )

afterEach( () => {
	filters.clear()
	delete ( globalThis as Record<symbol, unknown> )[ REGISTERED_KEY ]
} )

describe( 'registerBindingsAttribute', () => {
	it( 'registers the filter exactly once even if called twice', () => {
		registerBindingsAttribute()
		registerBindingsAttribute()

		expect( ( filters.get( 'blocks.registerBlockType' ) ?? [] ).length ).toBe( 1 )
	} )

	it( 'adds a bindings object attribute to a block that has no attributes', () => {
		registerBindingsAttribute()

		const out = runFilter( { name: 'demo/block' } )

		expect( out.attributes ).toMatchObject( {
			bindings: { type: 'object' },
		} )
	} )

	it( 'merges bindings into an existing attributes map without clobbering siblings', () => {
		registerBindingsAttribute()

		const out = runFilter( {
			name: 'demo/block',
			attributes: { icon: { type: 'string' } },
		} )

		expect( out.attributes ).toMatchObject( {
			icon:     { type: 'string' },
			bindings: { type: 'object' },
		} )
	} )

	it( 'leaves a block that already declares bindings untouched', () => {
		registerBindingsAttribute()

		const existing = {
			name:       'demo/block',
			attributes: { bindings: { type: 'object', default: { icon: { source: 'x' } } } },
		}

		const out = runFilter( existing )

		expect( out.attributes ).toBe( existing.attributes )
	} )
} )
