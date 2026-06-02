/**
 * Shared bridge between the state read-sync and write-interceptor (#511).
 *
 * Module-level coordination state used to keep the inspector's WP
 * panels (color / border / shadow) visually in sync with the active
 * interactive state, without persisting the synced overlay on save.
 *
 * Why a shared module: both pieces — `StateInspectorSync` (overlays
 * the merged view onto the data store so panels read it) and
 * `StateWriteInterceptor` (routes panel writes into the states bag) —
 * need to coordinate. A sync dispatch must be invisible to the
 * interceptor; a write must restore pristine before save and re-sync
 * after. The shared maps live here so neither component owns the
 * coordination protocol alone.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

/**
 * The "real" base values for state-eligible paths on a block that is
 * currently view-synced. Used to restore the data store before save
 * (so the saved markup keeps idle as the base) and on teardown when
 * the active state goes idle, the inspector closes, or selection
 * moves to a different block.
 *
 * Keyed by `clientId`. Each entry is a partial attribute tree
 * (built via `setPath`) holding only the paths that were overlaid.
 */
const pristineSnapshots = new Map<string, Record<string, unknown>>()

/**
 * Attribute shape the inspector-sync expects the data store to take
 * after its dispatch lands. When the write-interceptor's effect fires
 * and the selection's attributes match this expected shape, the
 * interceptor recognises it as a sync — not a panel write — and
 * skips routing (otherwise it would unwind the overlay back to the
 * states bag and the panel swatches would flicker back to idle).
 *
 * Keyed by `clientId`. Consumed and cleared by the write-interceptor.
 */
const expectedSyncedAttrs = new Map<string, Record<string, unknown>>()

export function setPristineSnapshot(
	clientId: string,
	snapshot: Record<string, unknown>,
): void {
	pristineSnapshots.set( clientId, snapshot )
}

export function getPristineSnapshot(
	clientId: string,
): Record<string, unknown> | undefined {
	return pristineSnapshots.get( clientId )
}

export function clearPristineSnapshot( clientId: string ): void {
	pristineSnapshots.delete( clientId )
}

export function hasPristineSnapshot( clientId: string ): boolean {
	return pristineSnapshots.has( clientId )
}

export function setExpectedSyncedAttrs(
	clientId: string,
	attributes: Record<string, unknown>,
): void {
	expectedSyncedAttrs.set( clientId, attributes )
}

export function consumeExpectedSyncedAttrs(
	clientId: string,
): Record<string, unknown> | undefined {
	const value = expectedSyncedAttrs.get( clientId )
	if ( undefined !== value ) {
		expectedSyncedAttrs.delete( clientId )
	}
	return value
}

/**
 * Returns all clientIds that currently have a pristine snapshot.
 * Used by `flushBeforeSave` to restore every block's pristine base
 * before host serialization.
 */
export function getAllPristineClientIds(): string[] {
	return Array.from( pristineSnapshots.keys() )
}

/**
 * Create or extend a pristine snapshot for `clientId`, recording the
 * value at each `path` in `attributes` — but only for paths not already
 * captured. Idempotent on revisits: subsequent calls for the same path
 * never overwrite the original capture.
 *
 * This complements {@link setPristineSnapshot}, which {@link
 * StateInspectorSync}'s own overlay flow uses to take a one-shot
 * snapshot keyed on the paths returned by its `planOverlay`. That flow
 * never captures the FIRST authored state pick, because at that
 * moment the states bag is still empty and `planOverlay` returns null.
 * `withStateAttributes` calls this function instead so the original
 * idle base values are captured before its mirror-to-base step
 * overwrites them.
 */
export function extendPristineSnapshot(
	clientId: string,
	attributes: Record<string, unknown>,
	paths: string[],
	readPath: ( source: unknown, path: string ) => unknown,
): void {
	let snapshot = pristineSnapshots.get( clientId )

	if ( ! snapshot ) {
		snapshot = {}
		pristineSnapshots.set( clientId, snapshot )
	}

	for ( const path of paths ) {
		if ( path in snapshot ) {
			continue
		}

		snapshot[ path ] = readPath( attributes, path )
	}
}

/**
 * Test-only — wipe coordination state between specs.
 */
export function resetStateBridge(): void {
	pristineSnapshots.clear()
	expectedSyncedAttrs.clear()
}
