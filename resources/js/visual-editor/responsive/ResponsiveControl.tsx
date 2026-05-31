/**
 * Per-breakpoint InspectorControls wrapper (#487).
 *
 * Renders any single-value control (number input, alignment toggle,
 * font-size picker, etc.) as a per-breakpoint control. The wrapper:
 *
 *  - Reads the value the editor is currently authoring against (the
 *    active breakpoint) via {@see useResponsiveValue}.
 *  - Persists writes through the {@see promote} migrator so scalars
 *    only inflate to the discriminated form on first override.
 *  - Surfaces a per-breakpoint "Reset to base" affordance that
 *    delegates to {@see clearOverride}.
 *
 * The actual control is passed in via a render prop so this wrapper
 * stays orthogonal to the visual primitive (which differs per
 * attribute — slider, select, text input, …).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveBreakpoint, subscribeActiveBreakpoint } from './active-breakpoint'
import { clearOverride, promote } from './migrator'
import type { BreakpointRegistry } from './registry'
import { resolveResponsiveValue } from './resolver'
import { BASE_KEY, type ResponsiveAttribute } from './types'

export interface ResponsiveControlProps<T> {
	registry: BreakpointRegistry
	value: ResponsiveAttribute<T> | null | undefined
	onChange: ( next: ResponsiveAttribute<T> | null ) => void
	/**
	 * Renders the underlying single-value control. Receives the value
	 * for the currently-active breakpoint (or the cascaded value) and
	 * a `setValue` callback that promotes/persists into the
	 * discriminated form.
	 */
	render: ( props: { value: T | null; setValue: ( next: T | null ) => void; breakpoint: string } ) => JSX.Element
	label?: string
}

export function ResponsiveControl<T>( {
	registry,
	value,
	onChange,
	render,
	label,
}: ResponsiveControlProps<T> ): JSX.Element {
	const activeBreakpoint = useSyncExternalStore( subscribeActiveBreakpoint, getActiveBreakpoint, getActiveBreakpoint )

	const resolved = resolveResponsiveValue<T>( value, activeBreakpoint, registry )

	const setValue = ( next: T | null ): void => {
		onChange( promote<T>( value, activeBreakpoint, next ) )
	}

	const resetOverride = (): void => {
		const cleared = clearOverride<T>( value, activeBreakpoint )
		onChange( cleared as ResponsiveAttribute<T> | null )
	}

	const isOverridden = BASE_KEY !== activeBreakpoint
		&& value !== null
		&& 'object' === typeof value
		&& null !== ( value as Record<string, unknown> )[ activeBreakpoint ]
		&& undefined !== ( value as Record<string, unknown> )[ activeBreakpoint ]

	return (
		<div className="ve-responsive-control" data-breakpoint={ activeBreakpoint }>
			{ label ? <div className="ve-responsive-control__label">{ label }</div> : null }

			{ render( { value: resolved, setValue, breakpoint: activeBreakpoint } ) }

			{ isOverridden ? (
				<button
					type="button"
					className="ve-responsive-control__reset"
					onClick={ resetOverride }
				>
					Reset to base
				</button>
			) : null }
		</div>
	)
}
