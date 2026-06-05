/**
 * Active-breakpoint state (#487).
 *
 * Tiny pub/sub store that holds which breakpoint the editor is
 * currently authoring against. Lives outside React so non-React
 * surfaces (canvas iframe wrapper, top-bar buttons rendered by Volt,
 * Vue host bridge) can read and write it without crossing the React
 * tree boundary.
 *
 * The React-friendly `useActiveBreakpoint()` hook in `./hooks` wraps
 * this with `useSyncExternalStore` so component re-renders happen
 * automatically when the editor changes breakpoints.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY } from './types'

type Listener = ( breakpoint: string ) => void

let current: string         = BASE_KEY
const listeners: Set<Listener> = new Set()

export function getActiveBreakpoint(): string {
	return current
}

export function setActiveBreakpoint( breakpoint: string ): void {
	if ( breakpoint === current ) {
		return
	}

	current = breakpoint
	listeners.forEach( ( listener ) => listener( current ) )
}

export function subscribeActiveBreakpoint( listener: Listener ): () => void {
	listeners.add( listener )

	return () => {
		listeners.delete( listener )
	}
}

/**
 * Test-only — reset back to `base` between specs. Production code
 * should set an explicit breakpoint instead.
 */
export function resetActiveBreakpoint(): void {
	current = BASE_KEY
	listeners.forEach( ( listener ) => listener( current ) )
}
