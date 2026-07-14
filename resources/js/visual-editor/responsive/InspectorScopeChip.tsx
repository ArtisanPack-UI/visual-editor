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
 * on what to call the active breakpoint. Consumers that don't have a
 * hydrated registry (dev/test paths, hosts still on the boot-time
 * defaults) fall through to a module-level fallback registry built
 * from `TAILWIND_V4_DEFAULTS`, so the chip still reads `Mobile` /
 * `Tablet` / `Desktop` for the ship keys instead of the raw slug.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveBreakpoint, setActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from './registry'
import { BASE_KEY } from './types'

import './inspector-scope-chip.css'

// Fallback registry used when the caller doesn't hydrate one from the
// PHP-side bootstrap payload. Ensures the chip reads a device-friendly
// label (`Mobile`, `Tablet`, `Desktop`) rather than the raw slug for
// the shipped keys, and stays consistent with the switcher's ship
// defaults.
const FALLBACK_REGISTRY = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

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

	const activeRegistry = registry ?? FALLBACK_REGISTRY
	const label          = activeRegistry.label( active )

	return (
		<div
			className="ap-visual-editor-inspector-scope-chip"
			role="status"
			aria-live="polite"
		>
			<span className="ap-visual-editor-inspector-scope-chip__label">
				Editing at <strong>{ label }</strong> and up
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
