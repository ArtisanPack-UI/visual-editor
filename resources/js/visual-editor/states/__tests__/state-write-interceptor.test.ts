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

	it( 'routes a top-level palette write into states and keeps base synced to the new value (#511)', () => {
		const correction = planCorrection(
			{ backgroundColor: 'vivid-purple' },
			{ backgroundColor: 'pale-pink' },
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		// Sync model: base mirrors the new value so the inspector
		// panel stays visually aligned with the active state.
		expect( correction!.updatePayload.backgroundColor ).toBe( 'pale-pink' )
		expect( correction!.updatePayload.states ).toEqual( {
			backgroundColor: { hover: 'pale-pink' },
		} )
		expect( correction!.correctedAttributes.backgroundColor ).toBe( 'pale-pink' )
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

	it( 'handles nested style.color.background paths with base synced to the new value', () => {
		const correction = planCorrection(
			{ style: { color: { background: '#000' } } },
			{ style: { color: { background: '#fff' } } },
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		const baseStyle = correction!.updatePayload.style as { color: { background: string } }
		expect( baseStyle.color.background ).toBe( '#fff' )
		expect( correction!.updatePayload.states ).toEqual( {
			'style.color.background': { hover: '#fff' },
		} )
	} )

	it( 'preserves style siblings when routing a nested path (#515)', () => {
		const correction = planCorrection(
			{
				style: {
					color:   { background: '#2eccc6' },
					spacing: { padding: '10px' },
					border:  { radius: '4px' },
				},
			},
			{
				style: {
					color:   { background: '#e11919' },
					spacing: { padding: '10px' },
					border:  { radius: '4px' },
				},
			},
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()

		const payloadStyle = correction!.updatePayload.style as Record<string, unknown>
		expect( ( payloadStyle.color as Record<string, unknown> ).background ).toBe( '#e11919' )
		// Siblings must survive the shallow merge dispatched by
		// updateBlockAttributes — otherwise spacing and border would
		// be clobbered.
		expect( ( payloadStyle.spacing as Record<string, unknown> ).padding ).toBe( '10px' )
		expect( ( payloadStyle.border as Record<string, unknown> ).radius ).toBe( '4px' )

		const correctedStyle = correction!.correctedAttributes.style as Record<string, unknown>
		expect( ( correctedStyle.spacing as Record<string, unknown> ).padding ).toBe( '10px' )
		expect( ( correctedStyle.border as Record<string, unknown> ).radius ).toBe( '4px' )
	} )

	it( 'routes a custom hex write on a non-idle state into states (#515)', () => {
		const correction = planCorrection(
			{
				style:  {
					color:   { background: '#2eccc6' },
					spacing: { padding: '10px' },
				},
				states: {
					'style.color.background': { hover: '#2eccc6' },
				},
			},
			{
				style:  {
					color:   { background: '#e11919' },
					spacing: { padding: '10px' },
				},
				states: {
					'style.color.background': { hover: '#2eccc6' },
				},
			},
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		expect( correction!.updatePayload.states ).toEqual( {
			'style.color.background': { hover: '#e11919' },
		} )

		const payloadStyle = correction!.updatePayload.style as Record<string, unknown>
		expect( ( payloadStyle.color as Record<string, unknown> ).background ).toBe( '#e11919' )
		expect( ( payloadStyle.spacing as Record<string, unknown> ).padding ).toBe( '10px' )
	} )

	it( 'routes multiple state-eligible changes in one diff and keeps base synced', () => {
		const correction = planCorrection(
			{ backgroundColor: 'a', textColor: 'b' },
			{ backgroundColor: 'a2', textColor: 'b2' },
			ROOTS,
			'hover',
		)

		expect( correction ).not.toBeNull()
		expect( correction!.updatePayload.backgroundColor ).toBe( 'a2' )
		expect( correction!.updatePayload.textColor ).toBe( 'b2' )
		expect( correction!.updatePayload.states ).toEqual( {
			backgroundColor: { hover: 'a2' },
			textColor:       { hover: 'b2' },
		} )
	} )
} )
