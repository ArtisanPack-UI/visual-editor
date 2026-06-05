/**
 * Viewport switcher (#487).
 *
 * Toolbar component that lets the editor preview the canvas at a
 * specific breakpoint AND scope subsequent style edits to that
 * breakpoint.
 *
 * Behavior:
 *  - Renders one button per registered breakpoint plus a `base`
 *    button on the left.
 *  - Selecting a button (a) updates the active-breakpoint store,
 *    (b) sets the canvas iframe's width to the breakpoint's
 *    min-width (or unconstrained for `base`), (c) keeps the
 *    selection visible via aria-pressed.
 *  - The actual canvas-resize side-effect is delegated to an
 *    `onChange` callback so different host shells (admin editor,
 *    sandbox, site-editor) can resize their own surface.
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
	/** Optional side-effect — typically resizes the canvas iframe. */
	onChange?: ( breakpoint: string, minWidthPx: number ) => void
	className?: string
	/** Override the visible labels (defaults to the breakpoint key, e.g. "sm"). */
	labels?: Record<string, string>
}

// Labels are intentionally size-keyed (`sm+`, `md+`) rather than
// device-named (`Mobile`, `Tablet`). The editor uses mobile-first
// cascade semantics — `base` applies everywhere, and each named
// breakpoint is a progressive override at that min-width and up. A
// label like "Mobile" for `sm` mis-suggests it scopes to phones only;
// the `+` suffix makes the "this size and up" semantics obvious.
const DEFAULT_LABELS: Record<string, string> = {
	[ BASE_KEY ]: 'All sizes',
	sm:          'sm+',
	md:          'md+',
	lg:          'lg+',
	xl:          'xl+',
	'2xl':       '2xl+',
}

function tooltipFor( key: string, minWidth: number ): string {
	if ( BASE_KEY === key ) {
		return 'Applies to every viewport. Smaller breakpoints inherit this value unless overridden.'
	}

	return `Applies at ${ minWidth }px and up (mobile-first cascade).`
}

export function ViewportSwitcher( { registry, onChange, className, labels }: ViewportSwitcherProps ): JSX.Element {
	const active  = useSyncExternalStore( subscribeActiveBreakpoint, getActiveBreakpoint, getActiveBreakpoint )
	const display = { ...DEFAULT_LABELS, ...( labels ?? {} ) }
	const keys    = registry.keysWithBase()

	const handleSelect = ( key: string ): void => {
		setActiveBreakpoint( key )

		if ( onChange ) {
			onChange( key, registry.get( key ) ?? 0 )
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
						{ display[ key ] ?? key }
					</button>
				)
			} ) }
		</div>
	)
}
