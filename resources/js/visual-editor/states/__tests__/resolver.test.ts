import { describe, expect, it } from 'vitest'

import { StateRegistry } from '../registry'
import {
	distinctStateOverrides,
	isStatefulAttribute,
	resolveStateValue,
} from '../resolver'

const registry = new StateRegistry()

describe( 'resolveStateValue', () => {
	it( 'returns scalars unchanged', () => {
		expect( resolveStateValue( 'red', 'hover', registry ) ).toBe( 'red' )
		expect( resolveStateValue( 4, 'hover', registry ) ).toBe( 4 )
	} )

	it( 'returns null when the attribute is null/undefined', () => {
		expect( resolveStateValue( null, 'hover', registry ) ).toBeNull()
		expect( resolveStateValue( undefined, 'hover', registry ) ).toBeNull()
	} )

	it( 'walks the inheritance chain through null slots', () => {
		const attribute = { idle: 'red', hover: 'blue', active: null }

		expect( resolveStateValue( attribute, 'active', registry ) ).toBe( 'blue' )
	} )

	it( 'returns the override at the active state when one exists', () => {
		const attribute = { idle: 'red', hover: 'blue' }

		expect( resolveStateValue( attribute, 'idle', registry ) ).toBe( 'red' )
		expect( resolveStateValue( attribute, 'hover', registry ) ).toBe( 'blue' )
		expect( resolveStateValue( attribute, 'active', registry ) ).toBe( 'blue' )
		expect( resolveStateValue( attribute, 'focus', registry ) ).toBe( 'red' )
	} )

	it( 'returns null when no slot in the chain is defined', () => {
		const attribute = { hover: 'blue' }

		expect( resolveStateValue( attribute, 'focus', registry ) ).toBeNull()
	} )

	it( 'falls back to idle when the active state is unknown', () => {
		const attribute = { idle: 'red', hover: 'blue' }

		expect( resolveStateValue( attribute, 'made-up', registry ) ).toBe( 'red' )
	} )
} )

describe( 'isStatefulAttribute', () => {
	it( 'recognises the discriminated shape via idle or any registry key', () => {
		expect( isStatefulAttribute( { idle: 'red' }, registry ) ).toBe( true )
		expect( isStatefulAttribute( { hover: 'blue' }, registry ) ).toBe( true )
		expect( isStatefulAttribute( 'red', registry ) ).toBe( false )
		expect( isStatefulAttribute( [ 'a', 'b' ], registry ) ).toBe( false )
		expect( isStatefulAttribute( null, registry ) ).toBe( false )
	} )
} )

describe( 'distinctStateOverrides', () => {
	it( 'collapses to only states that differ from their inheritance parent', () => {
		const attribute = { idle: 'red', hover: 'red', active: 'red', focus: 'blue' }

		expect( distinctStateOverrides( attribute, registry ) ).toEqual( {
			idle:  'red',
			focus: 'blue',
		} )
	} )

	it( 'includes idle whenever it has a non-null value', () => {
		expect( distinctStateOverrides( { idle: 'red' }, registry ) ).toEqual( { idle: 'red' } )
	} )

	it( 'wraps a scalar as idle-only', () => {
		expect( distinctStateOverrides( 'red', registry ) ).toEqual( { idle: 'red' } )
	} )
} )
