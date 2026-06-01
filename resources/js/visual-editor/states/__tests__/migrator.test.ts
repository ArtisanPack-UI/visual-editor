import { describe, expect, it } from 'vitest'

import { clearOverride, demote, promote } from '../migrator'

describe( 'promote', () => {
	it( 'returns the scalar unchanged when promoting to idle on a scalar', () => {
		expect( promote( 'red', 'idle', 'blue' ) ).toBe( 'blue' )
	} )

	it( 'promotes a scalar to a stateful object on first non-idle override', () => {
		expect( promote( 'red', 'hover', 'blue' ) ).toEqual( { idle: 'red', hover: 'blue' } )
	} )

	it( 'merges the new override into an existing stateful object', () => {
		expect(
			promote( { idle: 'red', hover: 'blue' }, 'focus', 'green' ),
		).toEqual( { idle: 'red', hover: 'blue', focus: 'green' } )
	} )

	it( 'overwrites an existing slot at the same state', () => {
		expect(
			promote( { idle: 'red', hover: 'blue' }, 'hover', 'orange' ),
		).toEqual( { idle: 'red', hover: 'orange' } )
	} )
} )

describe( 'demote', () => {
	it( 'demotes a stateful object back to scalar when only idle is set', () => {
		expect( demote( { idle: 'red', hover: null } ) ).toBe( 'red' )
	} )

	it( 'leaves stateful objects with multiple defined slots untouched', () => {
		const attribute = { idle: 'red', hover: 'blue' }

		expect( demote( attribute ) ).toBe( attribute )
	} )

	it( 'returns null when the attribute is null', () => {
		expect( demote( null ) ).toBeNull()
	} )
} )

describe( 'clearOverride', () => {
	it( 'clears a single state and demotes if it was the last override', () => {
		expect(
			clearOverride( { idle: 'red', hover: 'blue' }, 'hover' ),
		).toBe( 'red' )
	} )

	it( 'clears a state without demoting when other overrides remain', () => {
		expect(
			clearOverride( { idle: 'red', hover: 'blue', focus: 'green' }, 'hover' ),
		).toEqual( { idle: 'red', focus: 'green' } )
	} )

	it( 'sets idle to null when clearing the idle slot', () => {
		expect(
			clearOverride( { idle: 'red', hover: 'blue' }, 'idle' ),
		).toEqual( { idle: null, hover: 'blue' } )
	} )
} )
