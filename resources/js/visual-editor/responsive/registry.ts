/**
 * Client-side breakpoint registry (#487, #820).
 *
 * Mirrors the PHP `BreakpointRegistry` so the editor can resolve
 * cascades and emit class strings without round-tripping to the server.
 * Hydrated from the `data-breakpoints` snapshot the bootstrap stamps
 * from the merged PHP config.
 *
 * Since #820 the model is desktop-first: `base` is the desktop design,
 * and the `tablet` / `mobile` device entries (`maxWidthPx`) apply at
 * that size and below. The pre-#820 mobile-first keys (`sm` … `2xl`,
 * `minWidthPx`) stay registered as legacy entries so saved content keeps
 * rendering, but the viewport switcher only offers the device entries.
 *
 * Emission order ({@link prefixes}) is legacy ascending by min-width,
 * then devices descending by max-width, so narrower overrides come last.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { BASE_KEY, type Breakpoint, type BreakpointRegistrySnapshot } from './types'

/**
 * Desktop-first device breakpoints (#820). Preview widths sit inside
 * each range so the canvas preview matches the front end.
 */
export const DEVICE_DEFAULTS: Breakpoint[] = [
	{ key: 'tablet', maxWidthPx: 1023, previewWidthPx: 768, label: 'Tablet' },
	{ key: 'mobile', maxWidthPx: 767,  previewWidthPx: 375, label: 'Mobile' },
]

/**
 * Legacy mobile-first breakpoints, kept so content saved before #820
 * resolves and renders unchanged.
 */
export const LEGACY_DEFAULTS: Breakpoint[] = [
	{ key: 'sm',  minWidthPx: 640,  previewWidthPx: 375,  label: 'Mobile' },
	{ key: 'md',  minWidthPx: 768,  previewWidthPx: 768,  label: 'Tablet' },
	{ key: 'lg',  minWidthPx: 1024, previewWidthPx: 1440, label: 'Desktop' },
	{ key: 'xl',  minWidthPx: 1280, previewWidthPx: 1280, label: 'xl+' },
	{ key: '2xl', minWidthPx: 1536, previewWidthPx: 1536, label: '2xl+' },
]

/**
 * Package defaults: legacy entries plus the desktop-first devices.
 * Mirrors `BreakpointRegistry::DEFAULTS`.
 */
export const TAILWIND_V4_DEFAULTS: Breakpoint[] = [ ...LEGACY_DEFAULTS, ...DEVICE_DEFAULTS ]

function isDevice( bp: Breakpoint ): bp is Breakpoint & { maxWidthPx: number } {
	return typeof bp.maxWidthPx === 'number' && typeof bp.minWidthPx !== 'number'
}

export class BreakpointRegistry {
	protected readonly breakpoints: Breakpoint[]

	constructor( breakpoints: Breakpoint[] = TAILWIND_V4_DEFAULTS ) {
		const legacy  = breakpoints
			.filter( ( bp ) => ! isDevice( bp ) && typeof bp.minWidthPx === 'number' )
			.sort( ( a, b ) => ( a.minWidthPx as number ) - ( b.minWidthPx as number ) )
		const devices = breakpoints
			.filter( isDevice )
			.sort( ( a, b ) => b.maxWidthPx - a.maxWidthPx )

		this.breakpoints = [ ...legacy, ...devices ]
	}

	/** Every entry in emission order. */
	all(): Breakpoint[] {
		return [ ...this.breakpoints ]
	}

	/** Every key in emission order (legacy ascending, then devices descending). */
	prefixes(): string[] {
		return this.breakpoints.map( ( bp ) => bp.key )
	}

	/** Desktop-first device keys, largest first (#820). */
	devicePrefixes(): string[] {
		return this.breakpoints.filter( isDevice ).map( ( bp ) => bp.key )
	}

	/** Legacy mobile-first keys, ascending (#820). */
	legacyPrefixes(): string[] {
		return this.breakpoints.filter( ( bp ) => ! isDevice( bp ) ).map( ( bp ) => bp.key )
	}

	keysWithBase(): string[] {
		return [ BASE_KEY, ...this.prefixes() ]
	}

	/**
	 * Min-width of a legacy key, `0` for `base`, `null` for a device or
	 * unknown key.
	 */
	get( key: string ): number | null {
		if ( BASE_KEY === key ) {
			return 0
		}

		const found = this.find( key )
		return found && typeof found.minWidthPx === 'number' && ! isDevice( found ) ? found.minWidthPx : null
	}

	/** Max-width of a device key, else `null` (#820). */
	maxWidth( key: string ): number | null {
		const found = this.find( key )
		return found && isDevice( found ) ? found.maxWidthPx : null
	}

	isLegacy( key: string ): boolean {
		const found = this.find( key )
		return !! found && ! isDevice( found )
	}

	/**
	 * `(min-width:640px)` for a legacy key, `(max-width:767px)` for a
	 * device key, `null` for `base` / unknown. Mirrors
	 * `BreakpointRegistry::mediaQuery()`.
	 */
	mediaQuery( key: string, spaced = false ): string | null {
		const found = this.find( key )

		if ( ! found ) {
			return null
		}

		const separator = spaced ? ': ' : ':'

		return isDevice( found )
			? `(max-width${ separator }${ found.maxWidthPx }px)`
			: `(min-width${ separator }${ found.minWidthPx }px)`
	}

	/**
	 * Canvas preview width for a key (#617). Falls back to the entry's
	 * min- or max-width. `0` for `base`, `null` for unknown keys.
	 */
	previewWidth( key: string ): number | null {
		if ( BASE_KEY === key ) {
			return 0
		}

		const found = this.find( key )

		if ( ! found ) {
			return null
		}

		if ( typeof found.previewWidthPx === 'number' && found.previewWidthPx > 0 ) {
			return found.previewWidthPx
		}

		return ( isDevice( found ) ? found.maxWidthPx : found.minWidthPx ) ?? null
	}

	/**
	 * Display label for a key (#617), falling back to the key itself.
	 */
	label( key: string ): string {
		const found = this.find( key )

		if ( found && typeof found.label === 'string' && found.label !== '' ) {
			return found.label
		}

		return key
	}

	has( key: string ): boolean {
		return BASE_KEY === key || this.breakpoints.some( ( bp ) => bp.key === key )
	}

	/**
	 * Keys whose values apply at the active breakpoint, highest
	 * precedence first, ending with `base`. Mirrors
	 * `BreakpointRegistry::cascade()` (#820):
	 *
	 * - legacy key → itself and the smaller legacy keys;
	 * - device key → itself, the larger devices (Mobile inherits from
	 *   Tablet), then — when `includeLegacy` — the legacy keys whose
	 *   min-width also matches at its preview width.
	 */
	cascade( active: string, includeLegacy = true ): string[] {
		const found = this.find( active )

		if ( BASE_KEY === active || ! found ) {
			return [ BASE_KEY ]
		}

		const legacy = this.breakpoints.filter( ( bp ) => ! isDevice( bp ) )

		if ( ! isDevice( found ) ) {
			return [
				...legacy
					.filter( ( bp ) => ( bp.minWidthPx as number ) <= ( found.minWidthPx as number ) )
					.map( ( bp ) => bp.key )
					.reverse(),
				BASE_KEY,
			]
		}

		const preview = this.previewWidth( active ) ?? found.maxWidthPx
		const devices = this.breakpoints
			.filter( isDevice )
			.filter( ( bp ) => bp.maxWidthPx >= found.maxWidthPx )
			.map( ( bp ) => bp.key )
			.reverse()
		const matchingLegacy = includeLegacy
			? legacy
				.filter( ( bp ) => ( bp.minWidthPx as number ) <= preview )
				.map( ( bp ) => bp.key )
				.reverse()
			: []

		return [ ...devices, ...matchingLegacy, BASE_KEY ]
	}

	toJSON(): BreakpointRegistrySnapshot {
		return { breakpoints: this.all() }
	}

	protected find( key: string ): Breakpoint | undefined {
		return this.breakpoints.find( ( bp ) => bp.key === key )
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

let activeRegistry: BreakpointRegistry | null = null

/**
 * Publish the editor's hydrated registry (#820) so modules that run
 * outside the React tree — the responsive-attributes HOC, the style
 * emitter hooks — cascade against the host's configured breakpoints
 * instead of the package defaults. Called by the editor bootstraps.
 */
export function setResponsiveRegistry( registry: BreakpointRegistry | null ): void {
	activeRegistry = registry
}

/**
 * The registry published by {@link setResponsiveRegistry}, or the
 * package defaults before the editor has hydrated one.
 */
export function getResponsiveRegistry(): BreakpointRegistry {
	if ( null === activeRegistry ) {
		activeRegistry = new BreakpointRegistry()
	}

	return activeRegistry
}
