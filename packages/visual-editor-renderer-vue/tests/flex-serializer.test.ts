/**
 * #595 — Vue renderer flex serializer parity test.
 */

import { describe, expect, it } from 'vitest'

import { serializeFlex } from '../src/support/flex-serializer'
import fixtures from '../../../resources/js/visual-editor/blocks/_shared/flex-controls/fixtures.json'

interface Fixture {
	name: string
	input: unknown
	expected: {
		containerClasses: string[]
		itemClasses: string[]
		arbitraryRules: Array<{ className: string; property: string; value: string; breakpoint: string }>
	}
}

describe( 'flex-serializer (Vue renderer)', () => {
	const cases = ( fixtures as { fixtures: Fixture[] } ).fixtures

	it.each( cases.map( ( f ) => [ f.name, f ] as const ) )( 'fixture: %s', ( _name, fixture ) => {
		const result = serializeFlex( fixture.input )

		const expectedClasses = [
			...fixture.expected.containerClasses,
			...fixture.expected.itemClasses,
		]

		expect( result.classes ).toEqual( expectedClasses )
		expect( result.arbitraryRules ).toEqual( fixture.expected.arbitraryRules )
	} )
} )
