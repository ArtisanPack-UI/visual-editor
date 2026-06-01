/**
 * State-write interceptor (#488, #511).
 *
 * WordPress's newer block-support panels (color, border, shadow on
 * apiVersion 3 blocks) dispatch attribute writes directly via
 * `useDispatch( 'core/block-editor' ).updateBlockAttributes()` instead
 * of calling `props.setAttributes`. That bypasses any `editor.BlockEdit`
 * HOC in the prop chain, including {@link withStateAttributes}.
 *
 * This component plugs the gap. It subscribes to the selected block's
 * attributes via `useSelect`, diffs them against the previous render,
 * and — when the active state is non-idle and the diff lands on a
 * state-eligible attribute path — re-dispatches a correction that:
 *
 *   - Mirrors the new value into
 *     `attributes.states.<path>.<activeState>`.
 *   - Keeps the base attribute equal to the new value too, so WP's
 *     panels (which read the base via `useSelect`) stay in lockstep
 *     with the active state. The actual idle base is preserved in the
 *     bridge's `pristineSnapshots` and restored before save by the
 *     companion `StateInspectorSync` component.
 *
 * Loop guard: when the interceptor's effect observes attributes that
 * match the bridge's expected synced shape, it recognises the change
 * as a sync — not a panel write — consumes the sentinel, and exits
 * without routing.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { hasBlockSupport, getBlockType } from '@wordpress/blocks'
import { useDispatch, useSelect } from '@wordpress/data'
import { useEffect, useRef, useSyncExternalStore } from 'react'

import {
	diffPaths,
	pathMatchesAnyRoot,
	setPath,
} from '../responsive/attribute-paths'
import { getActiveState, subscribeActiveState } from './active-state'
import { consumeExpectedSyncedAttrs } from './state-bridge'
import { BASE_KEY } from './types'

interface StateSupports {
	attributes?: string[]
}

interface SelectedBlock {
	clientId: string | null
	name: string | null
	attributes: Record<string, unknown>
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

interface CorrectionResult {
	correctedAttributes: Record<string, unknown>
	updatePayload: Record<string, unknown>
}

/**
 * Compute the correction payload + the attribute shape we expect to
 * see after dispatching it. Returns `null` when no correction is
 * required.
 *
 * Sync model (#511): every state-eligible leaf write lands in BOTH
 * the per-state slot at `states.<path>.<activeState>` AND the base
 * attribute. The base mirror keeps WP's panels visually in lockstep
 * with the active state. `StateInspectorSync` is responsible for
 * restoring the pristine base before save so the persisted markup
 * still treats idle as the canonical base.
 */
export function planCorrection(
	prev: Record<string, unknown>,
	curr: Record<string, unknown>,
	roots: string[],
	activeState: string,
): CorrectionResult | null {
	const changedLeaves = diffPaths( curr, prev )
	if ( 0 === changedLeaves.length ) {
		return null
	}

	let basePatch: Record<string, unknown> | null = null
	let statesPatch: StatesByPath | null          = null

	for ( const { path, value } of changedLeaves ) {
		// Ignore writes to the states bag itself — those are either
		// the user's per-state edits already routed, or housekeeping
		// from `withStateStyles` (scopeId mint). Letting them through
		// would cause oscillation.
		if ( 'states' === path || path.startsWith( 'states.' ) ) {
			continue
		}

		if ( ! pathMatchesAnyRoot( path, roots ) ) {
			continue
		}

		statesPatch = statesPatch ?? {}
		const currentForPath = ( curr.states as StatesByPath | undefined | null )?.[ path ] ?? {}
		statesPatch[ path ] = {
			...currentForPath,
			[ activeState ]: value,
		}

		basePatch = setPath( basePatch ?? {}, path, value )
	}

	if ( ! statesPatch ) {
		return null
	}

	const nextStates = {
		...( curr.states as StatesByPath | undefined | null ?? {} ),
		...statesPatch,
	}

	const updatePayload: Record<string, unknown> = {
		...( basePatch ?? {} ),
		states: nextStates,
	}

	const correctedAttributes: Record<string, unknown> = {
		...curr,
		...( basePatch ?? {} ),
		states: nextStates,
	}

	return { correctedAttributes, updatePayload }
}

export function StateWriteInterceptor(): null {
	const activeState = useSyncExternalStore(
		subscribeActiveState,
		getActiveState,
		getActiveState,
	)

	const selection = useSelect( ( select ): SelectedBlock => {
		const store = select( 'core/block-editor' ) as
			| {
				  getSelectedBlockClientId?: () => string | null
				  getBlockName?: ( id: string ) => string | null
				  getBlockAttributes?: (
					  id: string,
				  ) => Record<string, unknown> | null
			  }
			| undefined

		const clientId = store?.getSelectedBlockClientId?.() ?? null
		if ( ! clientId ) {
			return { clientId: null, name: null, attributes: {} }
		}

		return {
			clientId,
			name:       store?.getBlockName?.( clientId ) ?? null,
			attributes: store?.getBlockAttributes?.( clientId ) ?? {},
		}
	}, [] )

	const { updateBlockAttributes } = useDispatch( 'core/block-editor' ) as {
		updateBlockAttributes: (
			clientId: string,
			updates: Record<string, unknown>,
		) => void
	}

	const prevAttributesRef = useRef<Record<string, unknown>>( selection.attributes )
	const prevClientIdRef   = useRef<string | null>( selection.clientId )

	useEffect( () => {
		// Block selection changed — anchor the diff to the new block's
		// current attrs so the next change isn't misread as a write
		// against the old block's snapshot.
		if ( selection.clientId !== prevClientIdRef.current ) {
			prevClientIdRef.current   = selection.clientId
			prevAttributesRef.current = selection.attributes
			return
		}

		if ( ! selection.clientId ) {
			prevAttributesRef.current = selection.attributes
			return
		}

		// `StateInspectorSync` stamps the attribute shape it expects
		// the data store to take immediately before dispatching its
		// overlay. If `selection.attributes` matches, treat the
		// change as a sync — not a panel write — and exit cleanly.
		// Without this hop the interceptor would diff the sync
		// dispatch and try to route it, undoing the overlay it just
		// landed.
		const expected = consumeExpectedSyncedAttrs( selection.clientId )
		if ( expected ) {
			const drift = diffPaths( selection.attributes, expected )
			if ( 0 === drift.length ) {
				prevAttributesRef.current = selection.attributes
				return
			}
		}

		if ( ! selection.name || BASE_KEY === activeState ) {
			prevAttributesRef.current = selection.attributes
			return
		}

		const roots = getStateRoots( selection.name )
		if ( ! roots ) {
			prevAttributesRef.current = selection.attributes
			return
		}

		const correction = planCorrection(
			prevAttributesRef.current,
			selection.attributes,
			roots,
			activeState,
		)

		if ( ! correction ) {
			prevAttributesRef.current = selection.attributes
			return
		}

		// Anchor the next diff to the corrected shape so we don't
		// re-route our own dispatch.
		prevAttributesRef.current = correction.correctedAttributes
		updateBlockAttributes( selection.clientId, correction.updatePayload )
	}, [
		activeState,
		selection.clientId,
		selection.name,
		selection.attributes,
		updateBlockAttributes,
	] )

	return null
}
