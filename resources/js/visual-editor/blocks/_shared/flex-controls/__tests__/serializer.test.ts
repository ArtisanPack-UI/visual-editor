/**
 * Flex serializer parity test (#595).
 *
 * Runs every fixture in `fixtures.json` through the TS serializer and
 * asserts byte-exact class strings + arbitrary-value rules. The same
 * fixtures are consumed by the Blade Pest suite, the React vitest
 * suite, and the Vue vitest suite.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { describe, expect, it } from 'vitest'

import { BreakpointRegistry } from '../../../../responsive/registry'
import { serializeFlexContainer, serializeFlexItem } from '../serializer'
import fixtures from '../fixtures.json'

interface Fixture {
	name: string
	input: unknown
	expected: {
		containerClasses: string[]
		itemClasses: string[]
		arbitraryRules: Array<{ className: string; property: string; value: string; breakpoint: string }>
	}
}

describe( 'flex serializer', () => {
	const registry = new BreakpointRegistry()
	const cases    = ( fixtures as { fixtures: Fixture[] } ).fixtures

	it.each( cases.map( ( f ) => [ f.name, f ] as const ) )( 'fixture: %s', ( _name, fixture ) => {
		const container = serializeFlexContainer( fixture.input as never, registry )
		const item      = serializeFlexItem( fixture.input as never, registry )

		expect( container.classes ).toEqual( fixture.expected.containerClasses )
		expect( item.classes ).toEqual( fixture.expected.itemClasses )

		const allRules = [ ...container.arbitraryRules, ...item.arbitraryRules ]
		expect( allRules ).toEqual( fixture.expected.arbitraryRules )
	} )

	it( 'returns empty result for null input', () => {
		const result = serializeFlexContainer( null, registry )
		expect( result.classes ).toEqual( [] )
		expect( result.arbitraryRules ).toEqual( [] )
	} )
} )
