import { describe, expect, it } from 'vitest'

import { DEFAULT_STATES, StateRegistry, registryFromSnapshot } from '../registry'

describe( 'StateRegistry', () => {
	it( 'defaults to the built-in state set when nothing is passed', () => {
		const registry = new StateRegistry()

		expect( registry.keys() ).toEqual( [
			'idle',
			'hover',
			'focus',
			'focus-visible',
			'active',
			'disabled',
		] )
	} )

	it( 'hoists idle to the front of iteration order', () => {
		const registry = new StateRegistry( [
			{
				key:            'hover',
				label:          'Hover',
				selector:       '&:hover',
				icon:           'cursor',
				inheritsFrom:   'idle',
				hoverMediaWrap: true,
			},
			{
				key:            'idle',
				label:          'Idle',
				selector:       '',
				icon:           'circle',
				inheritsFrom:   null,
				hoverMediaWrap: false,
			},
		] )

		expect( registry.keys()[ 0 ] ).toBe( 'idle' )
	} )

	it( 'returns the inheritance chain ending at idle', () => {
		const registry = new StateRegistry()

		expect( registry.inheritanceChain( 'active' ) ).toEqual( [ 'active', 'hover', 'idle' ] )
		expect( registry.inheritanceChain( 'focus-visible' ) ).toEqual( [ 'focus-visible', 'focus', 'idle' ] )
		expect( registry.inheritanceChain( 'idle' ) ).toEqual( [ 'idle' ] )
		expect( registry.inheritanceChain( 'made-up' ) ).toEqual( [ 'idle' ] )
	} )

	it( 'returns null for unknown states', () => {
		const registry = new StateRegistry()

		expect( registry.get( 'made-up' ) ).toBeNull()
	} )

	it( 'throws when constructed without an idle slot', () => {
		expect( () => new StateRegistry( DEFAULT_STATES.filter( ( s ) => 'idle' !== s.key ) ) ).toThrow(
			/idle/,
		)
	} )

	it( 'rebuilds from a snapshot or falls back to defaults', () => {
		expect( registryFromSnapshot( undefined ).keys() ).toEqual( [
			'idle',
			'hover',
			'focus',
			'focus-visible',
			'active',
			'disabled',
		] )

		const custom = registryFromSnapshot( {
			states: [
				...DEFAULT_STATES,
				{
					key:            'aria-current',
					label:          'Current',
					selector:       '&[aria-current="page"]',
					icon:           'flag',
					inheritsFrom:   'idle',
					hoverMediaWrap: false,
				},
			],
		} )

		expect( custom.keys() ).toContain( 'aria-current' )
		expect( custom.get( 'aria-current' )?.selector ).toBe( '&[aria-current="page"]' )
	} )
} )
