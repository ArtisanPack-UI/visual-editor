/**
 * State switcher (#488).
 *
 * Inspector-column strip that lets the editor pick which interactive
 * state subsequent style edits scope to. Lives at the top of the
 * inspector's Block tab so the controls below it visibly retarget
 * when the editor selects a state.
 *
 * Block-specific by design: a block that does NOT declare
 * `supports.artisanpackStates` renders a single short message instead
 * of the strip — the same component shape stays mounted so the
 * inspector doesn't reflow when selection changes between supporting
 * and non-supporting blocks.
 *
 * The preview toggle adjacent to the strip drives the canvas
 * preview-state store; it is a sibling concern so a block that does
 * not support states still has no preview controls.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'

import {
	getActiveState,
	getPreviewState,
	setActiveState,
	setPreviewState,
	subscribeActiveState,
	subscribePreviewState,
} from './active-state'
import type { StateRegistry } from './registry'
import { BASE_KEY, type StateDefinition, type StatefulAttribute } from './types'

import './state-switcher.css'

export interface StateSwitcherProps {
	registry: StateRegistry
	/**
	 * Whether the currently selected block opts into state styling
	 * via `supports.artisanpackStates`. When false, the switcher
	 * renders an explanatory message instead of the chip strip and
	 * the preview toggle is hidden.
	 */
	supportsStates: boolean
	/**
	 * Stateful attributes the block is storing. Used purely to
	 * surface the "has-override" dot on each chip — the switcher
	 * itself does not mutate them. Keyed by attribute path; values
	 * are the stateful object or scalar.
	 */
	attributes?: Record<string, StatefulAttribute<unknown> | null | undefined>
	/**
	 * Allow-list of state keys this block opts into. When omitted,
	 * every registered state is selectable. The reserved `idle` slot
	 * is always available.
	 */
	allowedStates?: string[]
	className?: string
}

function chipHasOverride(
	stateKey: string,
	attributes: Record<string, StatefulAttribute<unknown> | null | undefined> | undefined,
): boolean {
	if ( ! attributes || BASE_KEY === stateKey ) {
		return false
	}

	const states = ( attributes as Record<string, unknown> ).states as
		| Record<string, unknown>
		| null
		| undefined

	if ( ! states || 'object' !== typeof states || Array.isArray( states ) ) {
		return false
	}

	for ( const value of Object.values( states ) ) {
		if ( null === value || 'object' !== typeof value || Array.isArray( value ) ) {
			continue
		}

		const bag = value as Record<string, unknown>
		if ( null !== bag[ stateKey ] && undefined !== bag[ stateKey ] ) {
			return true
		}
	}

	return false
}

export function StateSwitcher( {
	registry,
	supportsStates,
	attributes,
	allowedStates,
	className,
}: StateSwitcherProps ): JSX.Element {
	const active  = useSyncExternalStore( subscribeActiveState, getActiveState, getActiveState )
	const preview = useSyncExternalStore( subscribePreviewState, getPreviewState, getPreviewState )

	if ( ! supportsStates ) {
		return (
			<p
				className={ className ?? 'ap-visual-editor-state-switcher__unsupported' }
				role="status"
			>
				This block doesn't support state styling. Switch to a button, link, or
				other interactive block to access hover, focus, and active states.
			</p>
		)
	}

	const chips: StateDefinition[] = registry.all().filter( ( definition ) => {
		if ( BASE_KEY === definition.key ) {
			return true
		}

		if ( ! allowedStates ) {
			return true
		}

		return allowedStates.includes( definition.key )
	} )

	const handleSelect = ( key: string ): void => {
		setActiveState( key )
	}

	const togglePreview = (): void => {
		if ( BASE_KEY === active ) {
			return
		}

		setPreviewState( active === preview ? null : active )
	}

	const previewActive = null !== preview && preview === active

	return (
		<div
			className={ className ?? 'ap-visual-editor-state-switcher' }
			role="group"
			aria-label="Block state"
			data-active-state={ active }
		>
			{ chips.map( ( definition ) => {
				const isActive    = definition.key === active
				const hasOverride = chipHasOverride( definition.key, attributes )

				return (
					<button
						key={ definition.key }
						type="button"
						className="ap-visual-editor-state-switcher__chip"
						aria-pressed={ isActive }
						data-state={ definition.key }
						data-has-override={ hasOverride ? 'true' : 'false' }
						onClick={ () => handleSelect( definition.key ) }
						title={ tooltipFor( definition ) }
					>
						<span aria-hidden="true" data-icon={ definition.icon } />
						<span>{ definition.label }</span>
					</button>
				)
			} ) }

			{ BASE_KEY !== active ? (
				<span className="ap-visual-editor-state-switcher__preview">
					<button
						type="button"
						aria-pressed={ previewActive }
						onClick={ togglePreview }
						title={
							previewActive
								? 'Stop simulating this state on the canvas'
								: 'Simulate this state on the canvas — saved content is unchanged'
						}
					>
						{ previewActive ? 'Stop preview' : 'Preview' }
					</button>
				</span>
			) : null }
		</div>
	)
}

function tooltipFor( definition: StateDefinition ): string {
	if ( BASE_KEY === definition.key ) {
		return 'Default styles — apply when no interactive state matches.'
	}

	if ( '' === definition.selector ) {
		return definition.label
	}

	return `${ definition.label } — ${ definition.selector.replaceAll( '&', 'block' ) }`
}
