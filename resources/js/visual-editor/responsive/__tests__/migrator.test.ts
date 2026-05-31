import { describe, expect, it } from 'vitest'

import { clearOverride, demote, promote } from '../migrator'

describe( 'promote', () => {
	it( 'leaves scalars unchanged when writing the base slot', () => {
		expect( promote( 4, 'base', 5 ) ).toBe( 5 )
	} )

	it( 'promotes a scalar into the discriminated form on first non-base override', () => {
		expect( promote( 4, 'md', 6 ) ).toEqual( { base: 4, md: 6 } )
	} )

	it( 'merges new overrides into an existing discriminated attribute', () => {
		const start = { base: 4, md: 6 }
		expect( promote( start, 'lg', 8 ) ).toEqual( { base: 4, md: 6, lg: 8 } )
	} )
} )

describe( 'demote', () => {
	it( 'collapses back to scalar when every override is null', () => {
		expect( demote( { base: 4, md: null, lg: null } ) ).toBe( 4 )
	} )

	it( 'leaves the attribute untouched when overrides remain', () => {
		const attr = { base: 4, md: 6 }
		expect( demote( attr ) ).toEqual( attr )
	} )
} )

describe( 'clearOverride', () => {
	it( 'clears a non-base override and demotes when nothing else remains', () => {
		expect( clearOverride( { base: 4, md: 6 }, 'md' ) ).toBe( 4 )
	} )

	it( 'clears a single override out of many and keeps the rest', () => {
		expect( clearOverride( { base: 4, sm: 1, md: 6 }, 'md' ) ).toEqual( {
			base: 4,
			sm:   1,
		} )
	} )
} )
