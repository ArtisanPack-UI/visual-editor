/**
 * Inspector view-state sync (#511).
 *
 * Keeps WordPress's color / border / shadow inspector panels visually
 * in lockstep with the active interactive state by overlaying the
 * merged view onto the selected block's base attributes in
 * `core/block-editor`. The panels read base attributes via `useSelect`
 * — bypassing the `editor.BlockEdit` HOC chain that
 * {@link withStateAttributes} sits on — so an HOC-only fix can't
 * reach them. Mirroring the merged value onto the base attribute is
 * the smallest change that makes them read the right thing.
 *
 * The original idle base values are stashed in the shared
 * {@link state-bridge} `pristineSnapshots` map and restored:
 *
 *   - When the active state returns to idle, so the inspector and
 *     canvas snap back to the canonical idle view.
 *   - When block selection changes, so we never leak one block's
 *     overlay onto another.
 *   - Around save — restoring the pristine base before serialization
 *     and re-applying the overlay after — so the persisted markup
 *     keeps idle as the canonical base.
 *
 * Coordination with {@link StateWriteInterceptor}: before each sync
 * dispatch, the expected post-dispatch attribute shape is stamped
 * into the bridge's `expectedSyncedAttrs` map; the interceptor
 * consumes the sentinel and skips routing for that tick. Panel
 * writes that happen later (no sentinel set) flow through the
 * interceptor's normal correction path.
 *
 * Sibling preservation: `updateBlockAttributes` shallow-merges at
 * the TOP level, so a partial like `{style: {color: {background}}}`
 * would clobber `style.spacing.padding`. Both the overlay dispatch
 * and the pristine restore therefore build patches by deep-cloning
 * each touched top-level subtree from the current attributes and
 * applying the state-eligible leaves on the clone. Pristine values
 * are snapshotted as a FLAT path map so `undefined` (the leaf was
 * never set) round-trips faithfully through restore.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { hasBlockSupport, getBlockType } from '@wordpress/blocks'
import {
	dispatch as dispatchStore,
	select as selectStore,
	useDispatch,
	useSelect,
} from '@wordpress/data'
import { useEffect, useRef, useSyncExternalStore } from 'react'

import {
	buildTopLevelPatch,
	pathMatchesAnyRoot,
	readPath,
} from '../responsive/attribute-paths'
import { getActiveState, subscribeActiveState } from './active-state'
import { getStateRegistry } from './registry'
import { resolveStateValue } from './resolver'
import {
	clearPristineSnapshot,
	getAllPristineClientIds,
	getPristineSnapshot,
	hasPristineSnapshot,
	setExpectedSyncedAttrs,
	setPristineSnapshot,
} from './state-bridge'
import { BASE_KEY } from './types'

interface StateSupports {
	attributes?: string[]
}

interface EditorStoreSlice {
	clientId: string | null
	name: string | null
	attributes: Record<string, unknown>
	isSaving: boolean
}

function getStateRoots( name: string | null ): string[] | null {
	if ( ! name ) {
		return null
	}

	const blockType = getBlockType( name )
	if ( ! blockType ) {
		return null
	}

	if ( ! hasBlockSupport( blockType, 'artisanpackStates', false ) ) {
		return null
	}

	const support = ( blockType.supports as Record<string, unknown> )
		.artisanpackStates

	if ( ! support || 'object' !== typeof support ) {
		return null
	}

	const roots = ( support as StateSupports ).attributes
	if ( ! Array.isArray( roots ) || 0 === roots.length ) {
		return null
	}

	return roots
}

type StatesByPath = Record<string, Record<string, unknown>>

interface OverlayPlan {
	entriesByTopKey: Map<string, Array<{ path: string; value: unknown }>>
	paths: string[]
}

/**
 * Walk the state bag and return the resolved overlay leaves at
 * `activeState` for every state-eligible path that has an authored
 * override. The flat `paths` list is the source of truth for the
 * pristine snapshot — using it (instead of walking the dispatched
 * overlay subtree) ensures non-state-eligible siblings that were
 * deep-cloned along for sibling preservation never make it into
 * the snapshot, where they would clobber any concurrent panel
 * writes on restore.
 */
function planOverlay(
	attributes: Record<string, unknown>,
	roots: string[],
	activeState: string,
): OverlayPlan | null {
	if ( BASE_KEY === activeState ) {
		return null
	}

	const states = ( attributes.states as StatesByPath | null | undefined ) ?? null
	if ( ! states || 'object' !== typeof states ) {
		return null
	}

	const registry = getStateRegistry()
	const entriesByTopKey = new Map<string, Array<{ path: string; value: unknown }>>()
	const paths: string[] = []

	for ( const [ path, value ] of Object.entries( states ) ) {
		if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
			// `_scopeId` and other housekeeping keys.
			continue
		}

		if ( ! pathMatchesAnyRoot( path, roots ) ) {
			continue
		}

		const baseValue = readPath( attributes, path )

		const stateful: Record<string, unknown> = {
			...( value as Record<string, unknown> ),
			[ BASE_KEY ]: baseValue,
		}

		const resolved = resolveStateValue<unknown>( stateful, activeState, registry )

		if ( null === resolved || undefined === resolved ) {
			continue
		}

		if ( resolved === baseValue ) {
			continue
		}

		const topKey = path.split( '.' )[ 0 ]
		const list   = entriesByTopKey.get( topKey ) ?? []
		list.push( { path, value: resolved } )
		entriesByTopKey.set( topKey, list )
		paths.push( path )
	}

	if ( 0 === entriesByTopKey.size ) {
		return null
	}

	return { entriesByTopKey, paths }
}

/**
 * Compute the overlay top-level patch that, when dispatched via
 * `updateBlockAttributes`, makes the data store reflect the merged
 * view at `activeState` for every state-eligible path that has an
 * authored override. Returns `null` when no change is required.
 */
export function buildOverlay(
	attributes: Record<string, unknown>,
	roots: string[],
	activeState: string,
): Record<string, unknown> | null {
	const plan = planOverlay( attributes, roots, activeState )
	if ( ! plan ) {
		return null
	}
	return buildTopLevelPatch( attributes, plan.entriesByTopKey )
}

/**
 * Snapshot pristine values for every state-eligible overlay leaf in
 * a flat path map so restore can unset paths that were `undefined`
 * at sync time. Captured once per sync session — subsequent ticks
 * reuse the initial snapshot so panel writes during sync (including
 * writes to non-state-eligible siblings inside the same subtree)
 * don't pollute it.
 *
 * Only the explicit `paths` are snapshotted — never the sibling
 * leaves that `buildTopLevelPatch` deep-cloned along to preserve
 * the rest of the subtree.
 */
export function snapshotPristineFor(
	clientId: string,
	attributes: Record<string, unknown>,
	paths: string[],
): void {
	if ( hasPristineSnapshot( clientId ) ) {
		return
	}

	const snapshot: Record<string, unknown> = {}

	for ( const path of paths ) {
		snapshot[ path ] = readPath( attributes, path )
	}

	setPristineSnapshot( clientId, snapshot )
}

export function applySyncDispatch(
	clientId: string,
	attributes: Record<string, unknown>,
	patch: Record<string, unknown>,
	updateBlockAttributes: (
		clientId: string,
		updates: Record<string, unknown>,
	) => void,
): void {
	// updateBlockAttributes shallow-merges, so the post-dispatch
	// store shape is `{...attributes, ...patch}`. Stamp that exactly
	// so the write-interceptor's drift check passes cleanly.
	const merged = { ...attributes, ...patch }
	setExpectedSyncedAttrs( clientId, merged )
	updateBlockAttributes( clientId, patch )
}

export function restorePristine(
	clientId: string,
	attributes: Record<string, unknown>,
	updateBlockAttributes: (
		clientId: string,
		updates: Record<string, unknown>,
	) => void,
): void {
	const snapshot = getPristineSnapshot( clientId )
	if ( ! snapshot ) {
		return
	}

	const entriesByTopKey = new Map<string, Array<{ path: string; value: unknown }>>()
	for ( const [ path, value ] of Object.entries( snapshot ) ) {
		const topKey = path.split( '.' )[ 0 ]
		const list   = entriesByTopKey.get( topKey ) ?? []
		list.push( { path, value } )
		entriesByTopKey.set( topKey, list )
	}

	const patch = buildTopLevelPatch( attributes, entriesByTopKey )

	applySyncDispatch( clientId, attributes, patch, updateBlockAttributes )
	clearPristineSnapshot( clientId )
}

/**
 * Host-agnostic save flush. Call this BEFORE serializing block
 * content whenever the host does not register `core/editor` (so
 * `isSavingPost` never fires). It restores every block's pristine
 * idle base into the data store, ensuring the saved markup keeps
 * idle as the canonical base attribute value.
 *
 * After serialization, the host should call this function's
 * companion — or re-render the affected blocks — to re-overlay
 * the active state's view.
 */
export function flushBeforeSave(): void {
	const clientIds = getAllPristineClientIds()
	if ( 0 === clientIds.length ) {
		return
	}

	const blockEditor = selectStore( 'core/block-editor' ) as
		| { getBlockAttributes?: ( id: string ) => Record<string, unknown> | null }
		| undefined
	const { updateBlockAttributes } = dispatchStore( 'core/block-editor' ) as {
		updateBlockAttributes: (
			clientId: string,
			updates: Record<string, unknown>,
		) => void
	}

	for ( const clientId of clientIds ) {
		const attrs = blockEditor?.getBlockAttributes?.( clientId ) ?? {}
		restorePristine( clientId, attrs, updateBlockAttributes )
	}
}

export function StateInspectorSync(): null {
	const activeState = useSyncExternalStore(
		subscribeActiveState,
		getActiveState,
		getActiveState,
	)

	const slice = useSelect( ( select ): EditorStoreSlice => {
		const blockEditor = select( 'core/block-editor' ) as
			| {
				  getSelectedBlockClientId?: () => string | null
				  getBlockName?: ( id: string ) => string | null
				  getBlockAttributes?: (
					  id: string,
				  ) => Record<string, unknown> | null
			  }
			| undefined

		const editor = select( 'core/editor' ) as
			| {
				  isSavingPost?: () => boolean
				  isAutosavingPost?: () => boolean
			  }
			| undefined

		const clientId = blockEditor?.getSelectedBlockClientId?.() ?? null

		return {
			clientId,
			name: clientId ? ( blockEditor?.getBlockName?.( clientId ) ?? null ) : null,
			attributes: clientId
				? ( blockEditor?.getBlockAttributes?.( clientId ) ?? {} )
				: {},
			isSaving: Boolean(
				editor?.isSavingPost?.() || editor?.isAutosavingPost?.(),
			),
		}
	}, [] )

	const { updateBlockAttributes } = useDispatch( 'core/block-editor' ) as {
		updateBlockAttributes: (
			clientId: string,
			updates: Record<string, unknown>,
		) => void
	}

	const prevClientIdRef    = useRef<string | null>( null )
	const prevActiveStateRef = useRef<string>( BASE_KEY )
	const prevIsSavingRef    = useRef<boolean>( false )

	useEffect( () => {
		const prevClientId    = prevClientIdRef.current
		const prevActiveState = prevActiveStateRef.current
		const prevIsSaving    = prevIsSavingRef.current

		// Save started — restore the pristine base so serialization
		// captures the canonical idle view. The overlay is reapplied
		// once the save finishes.
		if ( ! prevIsSaving && slice.isSaving && prevClientId === slice.clientId && prevClientId ) {
			restorePristine( prevClientId, slice.attributes, updateBlockAttributes )
		}

		const blockChanged       = slice.clientId !== prevClientId
		const activeStateChanged = activeState !== prevActiveState
		const saveLifecycleEnded = prevIsSaving && ! slice.isSaving

		// When the selected block changes, the PREVIOUS block's
		// pristine base must be restored so its data-store attributes
		// reflect the canonical idle view. Without this, serialization
		// at save time would persist the synced overlay values.
		if ( blockChanged && prevClientId && hasPristineSnapshot( prevClientId ) ) {
			const blockEditor = selectStore( 'core/block-editor' ) as
				| { getBlockAttributes?: ( id: string ) => Record<string, unknown> | null }
				| undefined
			const prevBlockAttrs = blockEditor?.getBlockAttributes?.( prevClientId ) ?? {}
			restorePristine( prevClientId, prevBlockAttrs, updateBlockAttributes )
		}

		if ( ! blockChanged && ! activeStateChanged && ! saveLifecycleEnded ) {
			prevClientIdRef.current    = slice.clientId
			prevActiveStateRef.current = activeState
			prevIsSavingRef.current    = slice.isSaving
			return
		}

		prevClientIdRef.current    = slice.clientId
		prevActiveStateRef.current = activeState
		prevIsSavingRef.current    = slice.isSaving

		if ( ! slice.clientId || ! slice.name ) {
			return
		}

		const roots = getStateRoots( slice.name )
		if ( ! roots ) {
			return
		}

		if ( BASE_KEY === activeState ) {
			restorePristine( slice.clientId, slice.attributes, updateBlockAttributes )
			return
		}

		// Non-idle — build the overlay against the pristine view
		// when a snapshot already exists, so re-runs (e.g. after a
		// save lifecycle completes) compute the overlay against the
		// unwound base rather than the already-synced attributes.
		const pristine = getPristineSnapshot( slice.clientId )
		let baselineAttributes: Record<string, unknown> = slice.attributes
		if ( pristine ) {
			const entriesByTopKey = new Map<string, Array<{ path: string; value: unknown }>>()
			for ( const [ path, value ] of Object.entries( pristine ) ) {
				const topKey = path.split( '.' )[ 0 ]
				const list   = entriesByTopKey.get( topKey ) ?? []
				list.push( { path, value } )
				entriesByTopKey.set( topKey, list )
			}
			const baselinePatch = buildTopLevelPatch( slice.attributes, entriesByTopKey )
			baselineAttributes  = { ...slice.attributes, ...baselinePatch }
		}

		const plan = planOverlay( baselineAttributes, roots, activeState )
		if ( ! plan ) {
			return
		}

		const overlay = buildTopLevelPatch( baselineAttributes, plan.entriesByTopKey )

		snapshotPristineFor( slice.clientId, baselineAttributes, plan.paths )
		applySyncDispatch( slice.clientId, slice.attributes, overlay, updateBlockAttributes )
	}, [
		activeState,
		slice.clientId,
		slice.name,
		slice.attributes,
		slice.isSaving,
		updateBlockAttributes,
	] )

	return null
}
