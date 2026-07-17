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

// Default labels for keys the ship defaults DO NOT relabel from the
// registry (`base`, `xl`, `2xl`). `sm`/`md`/`lg` get their
// `Mobile`/`Tablet`/`Desktop` labels from the registry entry itself
// (#617), so the switcher falls through to `registry.label(key)` for
// those. Registry labels are authored, so they take precedence over
// these fallbacks; the `labels` prop still wins over everything so
// hosts can override on a per-mount basis.
const DEFAULT_LABELS: Record<string, string> = {
	[ BASE_KEY ]: 'All sizes',
}

function tooltipFor( key: string, minWidth: number ): string {
	if ( BASE_KEY === key ) {
		return 'Applies to every viewport. Smaller breakpoints inherit this value unless overridden.'
	}

	return `Applies at ${ minWidth }px and up (mobile-first cascade).`
}

export function ViewportSwitcher( { registry, onChange, className, labels }: ViewportSwitcherProps ): JSX.Element {
	const active = useSyncExternalStore( subscribeActiveBreakpoint, getActiveBreakpoint, getActiveBreakpoint )
	const keys   = registry.keysWithBase()

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
				const minWidth = registry.get( key ) ?? 0

				return (
					<button
						key={ key }
						type="button"
						aria-pressed={ isActive }
						data-active={ isActive ? 'true' : 'false' }
						data-breakpoint={ key }
						title={ tooltipFor( key, minWidth ) }
						onClick={ () => handleSelect( key ) }
					>
						{ displayFor( key ) }
					</button>
				)
			} ) }
		</div>
	)
}
