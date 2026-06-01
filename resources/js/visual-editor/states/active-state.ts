/**
 * Active-state and preview-state stores (#488).
 *
 * Two tiny pub/sub stores that hold:
 *  - which state the editor's inspector is currently authoring against
 *    (`idle` by default), and
 *  - which state, if any, the canvas is simulating via the preview
 *    toggle.
 *
 * Both live outside React so non-React surfaces (canvas iframe
 * wrapper, top-bar buttons, Vue host bridge) can read and write them
 * without crossing the React tree boundary.
 *
 * The per-block scoping is enforced by clearing both stores whenever
 * the editor selects a different block — see {@see setActiveBlock()}.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY } from './types'

type Listener = ( state: string ) => void

let active: string                = BASE_KEY
let preview: string | null        = null
let currentBlockId: string | null = null

const activeListeners: Set<Listener> = new Set()
const previewListeners: Set<Listener> = new Set()

export function getActiveState(): string {
	return active
}

export function setActiveState( state: string ): void {
	if ( state === active ) {
		return
	}

	active = state
	activeListeners.forEach( ( listener ) => listener( active ) )
}

export function subscribeActiveState( listener: Listener ): () => void {
	activeListeners.add( listener )
	return () => {
		activeListeners.delete( listener )
	}
}

export function getPreviewState(): string | null {
	return preview
}

export function setPreviewState( state: string | null ): void {
	const normalized = ( null === state || BASE_KEY === state ) ? null : state

	if ( normalized === preview ) {
		return
	}

	preview = normalized
	previewListeners.forEach( ( listener ) => listener( preview ?? BASE_KEY ) )
}

export function subscribePreviewState( listener: Listener ): () => void {
	previewListeners.add( listener )
	return () => {
		previewListeners.delete( listener )
	}
}

/**
 * Per-block scope reset. The editor calls this whenever the selected
 * block changes so the inspector's state strip and the canvas
 * preview don't bleed from one block to the next.
 */
export function setActiveBlock( blockId: string | null ): void {
	if ( blockId === currentBlockId ) {
		return
	}

	currentBlockId = blockId
	setActiveState( BASE_KEY )
	setPreviewState( null )
}

export function getActiveBlock(): string | null {
	return currentBlockId
}

/**
 * Test-only — reset everything between specs. Production code uses
 * `setActiveBlock(null)` to clear when no block is selected.
 */
export function resetStateStores(): void {
	active         = BASE_KEY
	preview        = null
	currentBlockId = null
	activeListeners.forEach( ( listener ) => listener( active ) )
	previewListeners.forEach( ( listener ) => listener( BASE_KEY ) )
}
