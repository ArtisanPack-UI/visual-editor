/**
 * #595 — React renderer flex serializer parity test.
 *
 * Runs the shared fixtures from the editor package and asserts the
 * React renderer's class output matches byte-exact.
 */

import { describe, expect, it } from 'vitest'

import { buildArbitraryStyles, keywordOverrideRules, serializeFlex } from '../src/support/flex-serializer'
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

describe( 'flex-serializer (React renderer)', () => {
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

describe( 'keywordOverrideRules (#949)', () => {
	it( 'emits a breakpoint keyword after a competing arbitrary base value', () => {
		const result = serializeFlex( { item: { basis: { base: '280px', mobile: 'auto' } } } )

		expect( keywordOverrideRules( result ) ).toEqual( [
			{ className: 'mobile:ap-basis-auto', property: 'flex-basis', value: 'auto', breakpoint: 'mobile' },
		] )
		expect( buildArbitraryStyles( [ ...result.arbitraryRules, ...keywordOverrideRules( result ) ] ) )
			.toMatch( /\.ap-basis-\\\[280px\\\] \{ flex-basis: 280px; \} @media [^{]+\{ \.mobile\\:ap-basis-auto \{ flex-basis: auto; \} \}/ )
	} )

	it( 'leaves keyword classes alone when no arbitrary value competes on their property', () => {
		const result = serializeFlex( {
			container: { gap: { column: { base: '1rem' } } },
			item: { basis: { base: 'full', mobile: 'auto' } },
		} )

		expect( keywordOverrideRules( result ) ).toEqual( [] )
	} )
} )
