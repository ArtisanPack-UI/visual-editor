import { describe, expect, it } from 'vitest'

import {
	AnimationRegistry,
	DEFAULT_ANIMATIONS,
	registryFromSnapshot,
} from '../registry'
import { FAMILY_CONTINUOUS, FAMILY_ENTRANCE, FAMILY_HOVER } from '../types'

describe( 'AnimationRegistry', () => {
	it( 'defaults to the built-in set when nothing is passed', () => {
		const registry = new AnimationRegistry()

		expect( registry.has( FAMILY_ENTRANCE, 'fade-in' ) ).toBe( true )
		expect( registry.has( FAMILY_HOVER, 'lift' ) ).toBe( true )
		expect( registry.has( FAMILY_CONTINUOUS, 'pulse' ) ).toBe( true )
	} )

	it( 'exposes definitions by family', () => {
		const registry = new AnimationRegistry()

		const entrance = registry.family( FAMILY_ENTRANCE )
		expect( entrance.length ).toBeGreaterThan( 0 )
		expect( entrance.every( ( def ) => def.family === FAMILY_ENTRANCE ) ).toBe( true )
	} )

	it( 'returns null for unknown keys', () => {
		const registry = new AnimationRegistry()

		expect( registry.get( FAMILY_ENTRANCE, 'made-up' ) ).toBeNull()
	} )

	it( 'hydrates from a snapshot', () => {
		const registry = registryFromSnapshot( {
			animations: {
				[ FAMILY_ENTRANCE ]:   { 'fade-in': DEFAULT_ANIMATIONS[ FAMILY_ENTRANCE ][ 0 ] },
				[ FAMILY_HOVER ]:      {},
				[ FAMILY_CONTINUOUS ]: {},
			},
			customKeyframes: [ { name: 'confetti', stops: [
				{ at: '0%',   transform: 'translateY(0)' },
				{ at: '100%', transform: 'translateY(0)' },
			] } ],
		} )

		expect( registry.has( FAMILY_ENTRANCE, 'fade-in' ) ).toBe( true )
		expect( registry.customs().map( ( kf ) => kf.name ) ).toEqual( [ 'confetti' ] )
	} )

	it( 'returns an empty registry when given no snapshot', () => {
		const registry = registryFromSnapshot( undefined )

		expect( registry.has( FAMILY_ENTRANCE, 'fade-in' ) ).toBe( true )
	} )
} )
