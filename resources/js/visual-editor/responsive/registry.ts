/**
 * Client-side breakpoint registry (#487).
 *
 * Mirrors the PHP `BreakpointRegistry` so the editor can resolve
 * cascades and emit Tailwind class strings without round-tripping to
 * the server. Hydrated from `editor-settings`'s `breakpoints` array
 * which the bootstrap path stamps from the merged PHP config +
 * theme.json.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY, type Breakpoint, type BreakpointRegistrySnapshot } from './types'

export const TAILWIND_V4_DEFAULTS: Breakpoint[] = [
	{ key: 'sm', minWidthPx: 640 },
	{ key: 'md', minWidthPx: 768 },
	{ key: 'lg', minWidthPx: 1024 },
	{ key: 'xl', minWidthPx: 1280 },
	{ key: '2xl', minWidthPx: 1536 },
]

export class BreakpointRegistry {
	protected readonly breakpoints: Breakpoint[]

	constructor( breakpoints: Breakpoint[] = TAILWIND_V4_DEFAULTS ) {
		this.breakpoints = [ ...breakpoints ].sort( ( a, b ) => a.minWidthPx - b.minWidthPx )
	}

	all(): Breakpoint[] {
		return [ ...this.breakpoints ]
	}

	prefixes(): string[] {
		return this.breakpoints.map( ( bp ) => bp.key )
	}

	keysWithBase(): string[] {
		return [ BASE_KEY, ...this.prefixes() ]
	}

	get( key: string ): number | null {
		if ( BASE_KEY === key ) {
			return 0
		}

		const found = this.breakpoints.find( ( bp ) => bp.key === key )
		return found ? found.minWidthPx : null
	}

	has( key: string ): boolean {
		return BASE_KEY === key || this.breakpoints.some( ( bp ) => bp.key === key )
	}

	toJSON(): BreakpointRegistrySnapshot {
		return { breakpoints: this.all() }
	}
}

/**
 * Build a registry from a serialized snapshot (typically the JSON the
 * editor bootstrap stamps into window.artisanpackVisualEditor.settings).
 */
export function registryFromSnapshot( snapshot: BreakpointRegistrySnapshot | undefined ): BreakpointRegistry {
	if ( ! snapshot || ! Array.isArray( snapshot.breakpoints ) || 0 === snapshot.breakpoints.length ) {
		return new BreakpointRegistry()
	}

	return new BreakpointRegistry( snapshot.breakpoints )
}
