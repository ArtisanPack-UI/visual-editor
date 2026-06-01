import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType:    () => null,
	hasBlockSupport: () => false,
} ) )
vi.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { updateBlockAttributes: vi.fn() } ),
	useSelect:   () => ( {
		clientId:   null,
		name:       null,
		attributes: {},
		isSaving:   false,
	} ),
} ) )

import { buildOverlay } from '../StateInspectorSync'
import { resetStateBridge } from '../state-bridge'

beforeEach( () => {
	resetStateBridge()
} )

describe( 'buildOverlay (#511)', () => {
	it( 'returns null for idle', () => {
		expect(
			buildOverlay(
				{ backgroundColor: 'warning', states: { backgroundColor: { hover: 'error' } } },
				[ 'backgroundColor' ],
				'idle',
			),
		).toBeNull()
	} )

	it( 'returns null when no overrides exist', () => {
		expect(
			buildOverlay(
				{ backgroundColor: 'warning' },
				[ 'backgroundColor' ],
				'hover',
			),
		).toBeNull()
	} )

	it( 'overlays a top-level palette slug at the active state', () => {
		const overlay = buildOverlay(
			{
				backgroundColor: 'warning',
				states:          { backgroundColor: { hover: 'error' } },
			},
			[ 'backgroundColor' ],
			'hover',
		)

		expect( overlay ).toEqual( { backgroundColor: 'error' } )
	} )

	it( 'overlays nested style.color.background', () => {
		const overlay = buildOverlay(
			{
				style:  { color: { background: '#000' } },
				states: { 'style.color.background': { hover: '#fff' } },
			},
			[ 'style.color.background' ],
			'hover',
		)

		expect( overlay ).toEqual( { style: { color: { background: '#fff' } } } )
	} )

	it( 'inherits along the inheritance chain (active inherits from hover)', () => {
		const overlay = buildOverlay(
			{
				backgroundColor: 'warning',
				states:          { backgroundColor: { hover: 'error' } },
			},
			[ 'backgroundColor' ],
			'active',
		)

		expect( overlay ).toEqual( { backgroundColor: 'error' } )
	} )

	it( 'skips _scopeId and other housekeeping keys in the states bag', () => {
		const overlay = buildOverlay(
			{
				backgroundColor: 'warning',
				states:          {
					_scopeId:        'abc123',
					backgroundColor: { hover: 'error' },
				},
			},
			[ 'backgroundColor' ],
			'hover',
		)

		expect( overlay ).toEqual( { backgroundColor: 'error' } )
	} )

	it( 'skips paths not under the opt-in roots', () => {
		const overlay = buildOverlay(
			{
				states: {
					'style.spacing.padding': { hover: '20px' },
					backgroundColor:         { hover: 'error' },
				},
				backgroundColor: 'warning',
			},
			[ 'backgroundColor' ],
			'hover',
		)

		expect( overlay ).toEqual( { backgroundColor: 'error' } )
	} )

	it( 'skips overrides whose resolved value equals the base (no-op)', () => {
		const overlay = buildOverlay(
			{
				backgroundColor: 'warning',
				states:          { backgroundColor: { hover: 'warning' } },
			},
			[ 'backgroundColor' ],
			'hover',
		)

		expect( overlay ).toBeNull()
	} )

	it( 'preserves non-state-eligible siblings inside a touched subtree', () => {
		const overlay = buildOverlay(
			{
				style:  {
					color:   { background: '#000' },
					spacing: { padding: '20px' },
				},
				states: { 'style.color.background': { hover: '#fff' } },
			},
			[ 'style.color.background' ],
			'hover',
		)

		// The dispatched style subtree carries both the overlaid leaf
		// AND the untouched spacing sibling, so a shallow merge by
		// `updateBlockAttributes` does not clobber `padding`.
		expect( overlay ).toEqual( {
			style: {
				color:   { background: '#fff' },
				spacing: { padding: '20px' },
			},
		} )
	} )

	it( 'merges overlays for multiple state-eligible paths in one pass', () => {
		const overlay = buildOverlay(
			{
				backgroundColor: 'warning',
				textColor:       'foreground',
				states:          {
					backgroundColor: { hover: 'error' },
					textColor:       { hover: 'background' },
				},
			},
			[ 'backgroundColor', 'textColor' ],
			'hover',
		)

		expect( overlay ).toEqual( {
			backgroundColor: 'error',
			textColor:       'background',
		} )
	} )
} )
