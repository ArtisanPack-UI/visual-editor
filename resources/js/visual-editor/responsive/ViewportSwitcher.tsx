/**
 * Viewport switcher (#487 · #617).
 *
 * Unified device-preview + edit-scope toolbar. Selecting a button
 * atomically:
 *   1. Resizes the host canvas iframe to the breakpoint's
 *      `previewWidthPx` (#617 — delegated to the `onChange`
 *      callback so different host shells resize their own surface).
 *   2. Scopes subsequent style edits to that breakpoint key
 *      (#487 — mobile-first cascade semantics preserved).
 *
 * The `base` button previews at full editor width (no width
 * constraint, callback receives `0`) and scopes edits to the cascade
 * root.
 *
 * Display labels come from the registry entry's `label` field first,
 * falling back to the switcher's `DEFAULT_LABELS`, then the key itself.
 * The ship defaults expose `Mobile` / `Tablet` / `Desktop` for
 * `sm` / `md` / `lg` (#617) to match the WordPress site-editor
 * convention while keeping the internal keys stable so #487's
 * cascade language and `docs/responsive-design-tools.md` guidance
 * continue to work.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveBreakpoint, setActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import type { BreakpointRegistry } from './registry'
import { BASE_KEY } from './types'

export interface ViewportSwitcherProps {
	registry: BreakpointRegistry
	/**
	 * Side-effect fired when the user selects a preset. Receives the
	 * breakpoint key and the canvas preview width — the width is `0`
	 * for `base` (unconstrained), a positive int for named
	 * breakpoints. Host shells wire this to their canvas container's
	 * inline width (#617).
	 */
	onChange?: ( breakpoint: string, previewWidthPx: number ) => void
	className?: string
	/** Override the visible labels (highest priority, wins over registry labels). */
	labels?: Record<string, string>
}

// `base` has no registry entry, so it gets its label here. Device keys
// (`tablet`, `mobile`) take theirs from the registry entry (#617). The
// `labels` prop still wins over everything so hosts can override on a
// per-mount basis.
const DEFAULT_LABELS: Record<string, string> = {
	[ BASE_KEY ]: 'All sizes',
}

function tooltipFor( key: string, registry: BreakpointRegistry ): string {
	if ( BASE_KEY === key ) {
		return 'The desktop design. Applies at every width; Tablet and Mobile inherit it unless overridden.'
	}

	const maxWidth = registry.maxWidth( key )

	if ( null !== maxWidth ) {
		return `Applies at ${ maxWidth }px and below. Smaller devices inherit this value unless overridden.`
	}

	return `Applies at ${ registry.get( key ) ?? 0 }px and up.`
}

export function ViewportSwitcher( { registry, onChange, className, labels }: ViewportSwitcherProps ): JSX.Element {
	const active = useSyncExternalStore( subscribeActiveBreakpoint, getActiveBreakpoint, getActiveBreakpoint )
	// Only the desktop-first device breakpoints are offered (#820);
	// legacy mobile-first keys stay readable but aren't authored any more.
	const keys   = [ BASE_KEY, ...registry.devicePrefixes() ]

	const displayFor = ( key: string ): string => {
		if ( labels && key in labels ) {
			return labels[ key ] as string
		}

		if ( key in DEFAULT_LABELS ) {
			return DEFAULT_LABELS[ key ] as string
		}

		return registry.label( key )
	}

	const handleSelect = ( key: string ): void => {
		setActiveBreakpoint( key )

		if ( onChange ) {
			onChange( key, registry.previewWidth( key ) ?? 0 )
		}
	}

	return (
		<div className={ className ?? 've-viewport-switcher' } role="group" aria-label="Preview viewport">
			{ keys.map( ( key ) => {
				const isActive = key === active

				return (
					<button
						key={ key }
						type="button"
						aria-pressed={ isActive }
						data-active={ isActive ? 'true' : 'false' }
						data-breakpoint={ key }
						title={ tooltipFor( key, registry ) }
						onClick={ () => handleSelect( key ) }
					>
						{ displayFor( key ) }
					</button>
				)
			} ) }
		</div>
	)
}
