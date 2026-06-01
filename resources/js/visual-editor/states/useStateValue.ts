/**
 * `useStateValue` hook (#488).
 *
 * Resolves a stateful `{ idle, hover, … }` attribute against the
 * editor's currently active state. Block edit components consume
 * this to render the resolved value at the state the editor is
 * authoring against (or previewing).
 *
 * Preview state, when set, takes precedence over the active state —
 * the canvas should reflect what the editor is simulating.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import {
	getActiveState,
	getPreviewState,
	subscribeActiveState,
	subscribePreviewState,
} from './active-state'
import type { StateRegistry } from './registry'
import { resolveStateValue } from './resolver'
import type { StatefulAttribute } from './types'

/**
 * Returns the value for the given attribute at the editor's active
 * (or preview) state. Re-renders the component whenever the editor
 * switches states.
 */
export function useStateValue<T>(
	attribute: StatefulAttribute<T> | null | undefined,
	registry: StateRegistry,
): T | null {
	const active  = useSyncExternalStore( subscribeActiveState, getActiveState, getActiveState )
	const preview = useSyncExternalStore( subscribePreviewState, getPreviewState, getPreviewState )

	const target = preview ?? active

	return resolveStateValue<T>( attribute, target, registry )
}

/**
 * Read-only sibling of {@see useStateValue} for code paths that
 * already have an explicit state in hand (e.g. the renderer rebuilding
 * all states' values at once).
 */
export function resolveAt<T>(
	attribute: StatefulAttribute<T> | null | undefined,
	state: string,
	registry: StateRegistry,
): T | null {
	return resolveStateValue<T>( attribute, state, registry )
}
