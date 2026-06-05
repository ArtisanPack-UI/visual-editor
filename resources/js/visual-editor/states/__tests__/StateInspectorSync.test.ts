import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock( '@wordpress/blocks', () => ( {
	getBlockType:    () => null,
	hasBlockSupport: () => false,
} ) )
const mockUpdateBlockAttributes = vi.fn()
const mockGetBlockAttributes   = vi.fn( () => ( {} ) )

vi.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { updateBlockAttributes: vi.fn() } ),
	useSelect:   () => ( {
		clientId:   null,
		name:       null,
		attributes: {},
		isSaving:   false,
	} ),
	select:   () => ( { getBlockAttributes: mockGetBlockAttributes } ),
	dispatch: () => ( { updateBlockAttributes: mockUpdateBlockAttributes } ),
} ) )

import {
	applySyncDispatch,
	buildOverlay,
	flushBeforeSave,
	restorePristine,
	snapshotPristineFor,
} from '../StateInspectorSync'
import {
	consumeExpectedSyncedAttrs,
	extendPristineSnapshot,
	getPristineSnapshot,
	hasPristineSnapshot,
	resetStateBridge,
} from '../state-bridge'
import { readPath } from '../../responsive/attribute-paths'

beforeEach( () => {
	resetStateBridge()
	mockUpdateBlockAttributes.mockClear()
	mockGetBlockAttributes.mockReset()
	mockGetBlockAttributes.mockReturnValue( {} )
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

describe( 'sync → save → restore lifecycle (#511)', () => {
	const clientId = 'block-1'

	function makeAttributes(): Record<string, unknown> {
		return {
			backgroundColor: 'warning',
			style:           {
				color:   { background: '#e11919' },
				spacing: { padding: '20px' },
			},
			states: {
				_scopeId: 'abc123',
				'style.color.background': { hover: '#2eccc6' },
			},
		}
	}

	const roots = [ 'backgroundColor', 'color.background' ]

	it( 'snapshots pristine values once per session and ignores subsequent calls', () => {
		const attrs   = makeAttributes()
		const overlay = buildOverlay( attrs, roots, 'hover' )!

		snapshotPristineFor( clientId, attrs, [ 'style.color.background' ] )

		expect( hasPristineSnapshot( clientId ) ).toBe( true )
		const snapshot = getPristineSnapshot( clientId )
		expect( snapshot ).toEqual( { 'style.color.background': '#e11919' } )

		// A second call with the synced attrs must not overwrite the
		// pristine snapshot — otherwise the user's panel writes during
		// sync would pollute the pristine view used for save restore.
		const syncedAttrs = {
			...attrs,
			style: { ...( attrs.style as Record<string, unknown> ), color: { background: '#2eccc6' } },
		}
		const overlay2 = buildOverlay( syncedAttrs, roots, 'hover' )
		if ( overlay2 ) {
			snapshotPristineFor( clientId, syncedAttrs, [ 'style.color.background' ] )
		}

		expect( getPristineSnapshot( clientId ) ).toEqual( {
			'style.color.background': '#e11919',
		} )
	} )

	it( 'captures undefined for paths that were unset at sync time so restore can unset them', () => {
		const attrs: Record<string, unknown> = {
			backgroundColor: 'warning',
			states: { 'style.color.background': { hover: '#2eccc6' } },
		}
		const overlay = buildOverlay( attrs, roots, 'hover' )!

		snapshotPristineFor( clientId, attrs, [ 'style.color.background' ] )

		const snapshot = getPristineSnapshot( clientId )
		expect( snapshot ).toHaveProperty( 'style.color.background' )
		expect( snapshot![ 'style.color.background' ] ).toBeUndefined()
	} )

	it( 'restores pristine values on a top-level subtree without clobbering siblings', () => {
		const attrs   = makeAttributes()
		const overlay = buildOverlay( attrs, roots, 'hover' )!

		const dispatch = vi.fn()

		applySyncDispatch( clientId, attrs, overlay, dispatch )

		expect( dispatch ).toHaveBeenCalledWith( clientId, {
			style: {
				color:   { background: '#2eccc6' },
				spacing: { padding: '20px' },
			},
		} )

		// Synced attrs in the data store (what `useSelect` would now return).
		const syncedAttrs: Record<string, unknown> = {
			...attrs,
			style: {
				color:   { background: '#2eccc6' },
				spacing: { padding: '20px' },
			},
		}

		// Take a fresh snapshot if the sync would have set one — for the
		// lifecycle test we did NOT call snapshotPristineFor before
		// applySyncDispatch, so seed it manually here so restorePristine
		// has something to unwind.
		snapshotPristineFor( clientId, attrs, [ 'style.color.background' ] )

		dispatch.mockClear()

		restorePristine( clientId, syncedAttrs, dispatch )

		expect( dispatch ).toHaveBeenCalledTimes( 1 )
		const [ dispatchedClientId, patch ] = dispatch.mock.calls[ 0 ]
		expect( dispatchedClientId ).toBe( clientId )

		const restoredStyle = ( patch as Record<string, unknown> ).style as Record<
			string,
			Record<string, unknown>
		>

		expect( restoredStyle.color.background ).toBe( '#e11919' )
		// The padding sibling MUST survive the restore — otherwise any
		// non-state-eligible edits made during sync would be lost on save.
		expect( restoredStyle.spacing.padding ).toBe( '20px' )

		// Restore clears the snapshot so the next sync session starts fresh.
		expect( hasPristineSnapshot( clientId ) ).toBe( false )
	} )

	it( 'stamps the expected post-dispatch shape so the write-interceptor can detect the sync', () => {
		const attrs   = makeAttributes()
		const overlay = buildOverlay( attrs, roots, 'hover' )!

		const dispatch = vi.fn()
		applySyncDispatch( clientId, attrs, overlay, dispatch )

		const expected = consumeExpectedSyncedAttrs( clientId )
		expect( expected ).not.toBeUndefined()
		// `applySyncDispatch` uses a shallow merge to match
		// `updateBlockAttributes` reducer behaviour — top-level keys in
		// the overlay replace the corresponding keys on `attrs`.
		expect( ( expected as Record<string, unknown> ).style ).toEqual( {
			color:   { background: '#2eccc6' },
			spacing: { padding: '20px' },
		} )

		// The sentinel is consumed by the read so a follow-up write
		// (panel pick) flows through the interceptor's correction path.
		expect( consumeExpectedSyncedAttrs( clientId ) ).toBeUndefined()
	} )

	it( 'restoring without a snapshot is a no-op', () => {
		const dispatch = vi.fn()
		restorePristine( clientId, makeAttributes(), dispatch )
		expect( dispatch ).not.toHaveBeenCalled()
	} )
} )

describe( 'flushBeforeSave (#515)', () => {
	it( 'restores pristine for every block that has a snapshot', () => {
		const blockA = 'block-a'
		const blockB = 'block-b'

		snapshotPristineFor( blockA, { backgroundColor: 'idle-a' }, [ 'backgroundColor' ] )
		snapshotPristineFor( blockB, { backgroundColor: 'idle-b' }, [ 'backgroundColor' ] )

		mockGetBlockAttributes.mockImplementation( ( id: string ) => {
			if ( 'block-a' === id ) {
				return { backgroundColor: 'hover-a', states: {} }
			}
			if ( 'block-b' === id ) {
				return { backgroundColor: 'hover-b', states: {} }
			}
			return {}
		} )

		flushBeforeSave()

		expect( mockUpdateBlockAttributes ).toHaveBeenCalledTimes( 2 )
		expect( hasPristineSnapshot( blockA ) ).toBe( false )
		expect( hasPristineSnapshot( blockB ) ).toBe( false )
	} )

	it( 'is a no-op when no snapshots exist', () => {
		flushBeforeSave()
		expect( mockUpdateBlockAttributes ).not.toHaveBeenCalled()
	} )
} )

describe( 'extendPristineSnapshot (#515 follow-up)', () => {
	it( 'creates a fresh snapshot when none exists', () => {
		extendPristineSnapshot(
			'cid-1',
			{ backgroundColor: 'palette-red' },
			[ 'backgroundColor' ],
			readPath,
		)

		expect( getPristineSnapshot( 'cid-1' ) ).toEqual( {
			backgroundColor: 'palette-red',
		} )
	} )

	it( 'extends an existing snapshot with new paths', () => {
		extendPristineSnapshot(
			'cid-1',
			{ backgroundColor: 'palette-red' },
			[ 'backgroundColor' ],
			readPath,
		)
		extendPristineSnapshot(
			'cid-1',
			{ backgroundColor: 'palette-red', textColor: 'palette-blue' },
			[ 'textColor' ],
			readPath,
		)

		expect( getPristineSnapshot( 'cid-1' ) ).toEqual( {
			backgroundColor: 'palette-red',
			textColor:       'palette-blue',
		} )
	} )

	it( 'never overwrites an already-captured path', () => {
		extendPristineSnapshot(
			'cid-1',
			{ backgroundColor: 'palette-red' },
			[ 'backgroundColor' ],
			readPath,
		)
		// Simulate a second pick on the same path after the base has
		// been mirrored to the hover value — the snapshot must still
		// hold the ORIGINAL idle value.
		extendPristineSnapshot(
			'cid-1',
			{ backgroundColor: 'palette-blue' },
			[ 'backgroundColor' ],
			readPath,
		)

		expect( getPristineSnapshot( 'cid-1' ) ).toEqual( {
			backgroundColor: 'palette-red',
		} )
	} )

	it( 'captures undefined for paths the block has never set', () => {
		extendPristineSnapshot(
			'cid-1',
			{ /* no backgroundColor */ },
			[ 'backgroundColor' ],
			readPath,
		)

		const snapshot = getPristineSnapshot( 'cid-1' )
		expect( snapshot ).not.toBeUndefined()
		expect( 'backgroundColor' in ( snapshot as Record<string, unknown> ) ).toBe( true )
		expect( ( snapshot as Record<string, unknown> ).backgroundColor ).toBeUndefined()
	} )
} )
