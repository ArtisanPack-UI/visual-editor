import { describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType:    () => null,
	hasBlockSupport: () => false,
} ) )
vi.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { updateBlockAttributes: vi.fn() } ),
	useSelect:   () => ( { clientId: null, name: null, attributes: {} } ),
} ) )

import { planCorrection } from '../state-write-interceptor'

const ROOTS = [ 'backgroundColor', 'textColor', 'style.color.background' ]

describe( 'planCorrection', () => {
	it( 'returns null when nothing changed', () => {
		expect(
			planCorrection(
				{ backgroundColor: 'vivid-purple' },
				{ backgroundColor: 'vivid-purple' },
				ROOTS,
				'hover',
			),
		).toBeNull()
	} )

	it( 'returns null when the change is outside the state roots', () => {
		expect(
			planCorrection(
				{ url: 'https://a.test' },
				{ url: 'https://b.test' },
				ROOTS,
				'hover',
			),
		).toBeNull()
	} )

	it( 'routes a top-level palette write into states and restores the base', () => {
		const correction = planCorrection(
			{ backgroundColor: 'vivid-purple' },
			{ backgroundColor: 'pale-pink' },
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		expect( correction!.updatePayload.backgroundColor ).toBe( 'vivid-purple' )
		expect( correction!.updatePayload.states ).toEqual( {
			backgroundColor: { hover: 'pale-pink' },
		} )
		expect( correction!.correctedAttributes.backgroundColor ).toBe( 'vivid-purple' )
		expect( correction!.correctedAttributes.states ).toEqual( {
			backgroundColor: { hover: 'pale-pink' },
		} )
	} )

	it( 'preserves prior state overrides when adding a new one', () => {
		const correction = planCorrection(
			{
				backgroundColor: 'vivid-purple',
				states:          { backgroundColor: { focus: 'red' } },
			},
			{
				backgroundColor: 'pale-pink',
				states:          { backgroundColor: { focus: 'red' } },
			},
			ROOTS,
			'hover',
		)

		expect( correction!.updatePayload.states ).toEqual( {
			backgroundColor: { focus: 'red', hover: 'pale-pink' },
		} )
	} )

	it( 'ignores changes to attributes.states itself (no oscillation)', () => {
		expect(
			planCorrection(
				{ backgroundColor: 'vivid-purple', states: {} },
				{
					backgroundColor: 'vivid-purple',
					states:          { backgroundColor: { hover: 'pale-pink' } },
				},
				ROOTS,
				'hover',
			),
		).toBeNull()
	} )

	it( 'handles nested style.color.background paths', () => {
		const correction = planCorrection(
			{ style: { color: { background: '#000' } } },
			{ style: { color: { background: '#fff' } } },
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		const restoredStyle = correction!.updatePayload.style as { color: { background: string } }
		expect( restoredStyle.color.background ).toBe( '#000' )
		expect( correction!.updatePayload.states ).toEqual( {
			'style.color.background': { hover: '#fff' },
		} )
	} )

	it( 'routes multiple state-eligible changes in one diff', () => {
		const correction = planCorrection(
			{ backgroundColor: 'a', textColor: 'b' },
			{ backgroundColor: 'a2', textColor: 'b2' },
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		expect( correction!.updatePayload.backgroundColor ).toBe( 'a' )
		expect( correction!.updatePayload.textColor ).toBe( 'b' )
		expect( correction!.updatePayload.states ).toEqual( {
			backgroundColor: { hover: 'a2' },
			textColor:       { hover: 'b2' },
		} )
	} )
} )
