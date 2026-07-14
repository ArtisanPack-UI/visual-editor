/**
 * Client-side breakpoint registry (#487).
 *
 * Mirrors the PHP `BreakpointRegistry` so the editor can resolve
 * cascades and emit Tailwind class strings without round-tripping to
 * the server. Hydrated from `editor-settings`'s `breakpoints` array
 * which the bootstrap path stamps from the merged PHP config +
 * theme.json.
 *
 * #617 extends each entry with an optional `label` (display string)
 * and `previewWidthPx` (canvas iframe width). Both are optional and
 * fall back to sensible defaults on read (`label → key`,
 * `previewWidthPx → minWidthPx`), so pre-#617 snapshots that ship
 * only `{ key, minWidthPx }` continue to work without migration.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY, type Breakpoint, type BreakpointRegistrySnapshot } from './types'

export const TAILWIND_V4_DEFAULTS: Breakpoint[] = [
	{ key: 'sm',  minWidthPx: 640,  previewWidthPx: 375,  label: 'Mobile' },
	{ key: 'md',  minWidthPx: 768,  previewWidthPx: 768,  label: 'Tablet' },
	{ key: 'lg',  minWidthPx: 1024, previewWidthPx: 1440, label: 'Desktop' },
	// `xl+` / `2xl+` preserve the pre-#617 cascade signal (`this size
	// and up`) for the two breakpoints without a device-friendly name
	// — the mobile-first affordance stays visible for developers
	// auditing which class prefixes emit.
	{ key: 'xl',  minWidthPx: 1280, previewWidthPx: 1280, label: 'xl+' },
	{ key: '2xl', minWidthPx: 1536, previewWidthPx: 1536, label: '2xl+' },
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

	/**
	 * Returns the canvas preview width for a key (#617). Falls back
	 * to `minWidthPx` when the entry omits `previewWidthPx`. Returns
	 * `0` for `base` (no width constraint) and `null` for unknown
	 * keys, matching the semantics of {@see get()}.
	 */
	previewWidth( key: string ): number | null {
		if ( BASE_KEY === key ) {
			return 0
		}

		const found = this.breakpoints.find( ( bp ) => bp.key === key )

		if ( ! found ) {
			return null
		}

		return typeof found.previewWidthPx === 'number' && found.previewWidthPx > 0
			? found.previewWidthPx
			: found.minWidthPx
	}

	/**
	 * Returns the display label for a key (#617). Falls back to the
	 * key itself when no label is registered. `base` has no entry in
	 * the registry so callers supply their own base label.
	 */
	label( key: string ): string {
		const found = this.breakpoints.find( ( bp ) => bp.key === key )

		if ( found && typeof found.label === 'string' && found.label !== '' ) {
			return found.label
		}

		return key
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
