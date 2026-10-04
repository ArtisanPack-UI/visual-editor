/**
 * Inspector scope chip (#487 · #617).
 *
 * Small banner rendered at the top of the inspector's Block tab. When
 * the editor's active breakpoint is not `base`, it tells the editor
 * which viewport their next style edit will apply to — otherwise the
 * inspector controls would look like they were silently changing the
 * default value when they're actually scoped.
 *
 * Hidden entirely at `base` so the inspector stays uncluttered during
 * normal editing.
 *
 * #617 — labels now come from the same `BreakpointRegistry` the
 * viewport switcher renders, so the chip and switcher can't disagree
 * on what to call the active breakpoint. Without a `registry` prop the
 * chip reads the registry the editor published via
 * `setResponsiveRegistry()` (the package defaults before hydration).
 *
 * #820 — device overrides read "Editing at Mobile and down", matching
 * the desktop-first `max-width` cascade.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveBreakpoint, setActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import { type BreakpointRegistry, getResponsiveRegistry } from './registry'
import { BASE_KEY } from './types'

import './inspector-scope-chip.css'


export interface InspectorScopeChipProps {
	/**
	 * Optional breakpoint registry — typically the same instance the
	 * host passes to `TopBar`'s `viewportRegistry`. When provided, the
	 * chip renders the registry's label (e.g. an author-configured
	 * `iPhone`); when omitted, the chip uses the shipped defaults.
	 */
	registry?: BreakpointRegistry
}

export function InspectorScopeChip( { registry }: InspectorScopeChipProps = {} ): JSX.Element | null {
	const active = useSyncExternalStore(
		subscribeActiveBreakpoint,
		getActiveBreakpoint,
		getActiveBreakpoint,
	)

	if ( BASE_KEY === active ) {
		return null
	}

	const activeRegistry = registry ?? getResponsiveRegistry()
	const label          = activeRegistry.label( active )
	// Device overrides apply at that size and below (#820); a legacy
	// mobile-first key, if one is ever active, still reads "and up".
	const direction      = activeRegistry.isLegacy( active ) ? 'and up' : 'and down'

	return (
		<div
			className="ap-visual-editor-inspector-scope-chip"
			role="status"
			aria-live="polite"
		>
			<span className="ap-visual-editor-inspector-scope-chip__label">
				Editing at <strong>{ label }</strong> { direction }
				<span className="ap-visual-editor-inspector-scope-chip__key">
					({ active })
				</span>
			</span>
			<button
				type="button"
				className="ap-visual-editor-inspector-scope-chip__reset"
				onClick={ () => setActiveBreakpoint( BASE_KEY ) }
			>
				Switch to All sizes
			</button>
		</div>
	)
}
