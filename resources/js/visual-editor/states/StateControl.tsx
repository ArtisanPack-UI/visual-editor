/**
 * Per-state InspectorControls wrapper (#488).
 *
 * Renders any single-value control (color picker, border-radius
 * input, transform field, …) as a per-state control. The wrapper:
 *
 *  - Reads the value the editor is currently authoring against (the
 *    active state, or the preview state when previewing) via
 *    {@see useStateValue}.
 *  - Persists writes through the {@see promote} migrator so scalars
 *    only inflate to the stateful form on first override.
 *  - Surfaces a per-state "Reset" affordance that delegates to
 *    {@see clearOverride}.
 *
 * The actual control is passed in via a render prop so this wrapper
 * stays orthogonal to the visual primitive (slider, select, text
 * input, …).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import { getActiveState, subscribeActiveState } from './active-state'
import { clearOverride, promote } from './migrator'
import type { StateRegistry } from './registry'
import { useStateValue } from './useStateValue'
import { BASE_KEY, type StatefulAttribute } from './types'

export interface StateControlProps<T> {
	registry: StateRegistry
	value: StatefulAttribute<T> | null | undefined
	onChange: ( next: StatefulAttribute<T> | null ) => void
	/**
	 * Renders the underlying single-value control. Receives the value
	 * for the currently-active state (cascaded through inheritance)
	 * and a `setValue` callback that promotes/persists.
	 */
	render: ( props: { value: T | null; setValue: ( next: T | null ) => void; state: string } ) => JSX.Element
	label?: string
}

export function StateControl<T>( {
	registry,
	value,
	onChange,
	render,
	label,
}: StateControlProps<T> ): JSX.Element {
	// Subscribe to the active state directly for the *write* slot —
	// writes always target the chip-strip's active state, never the
	// preview state. Reads, however, go through {@link useStateValue}
	// so the surfaced value reflects whichever state the editor is
	// currently simulating (active or preview).
	const activeState = useSyncExternalStore( subscribeActiveState, getActiveState, getActiveState )
	const resolved    = useStateValue<T>( value, registry )

	const setValue = ( next: T | null ): void => {
		onChange( promote<T>( value, activeState, next ) )
	}

	const resetOverride = (): void => {
		const cleared = clearOverride<T>( value, activeState )
		onChange( cleared as StatefulAttribute<T> | null )
	}

	const isOverridden = BASE_KEY !== activeState
		&& null !== value
		&& undefined !== value
		&& 'object' === typeof value
		&& null !== ( value as Record<string, unknown> )[ activeState ]
		&& undefined !== ( value as Record<string, unknown> )[ activeState ]

	const stateLabel = registry.get( activeState )?.label ?? activeState

	return (
		<div className="ve-state-control" data-state={ activeState }>
			{ label ? <div className="ve-state-control__label">{ label }</div> : null }

			{ render( { value: resolved, setValue, state: activeState } ) }

			{ isOverridden ? (
				<button
					type="button"
					className="ve-state-control__reset"
					onClick={ resetOverride }
				>
					Reset { stateLabel }
				</button>
			) : null }
		</div>
	)
}
