/**
 * Preview-state toolbar toggle (#488).
 *
 * Top-bar toolbar component that lets the editor simulate any
 * registered interactive state on the canvas without actually
 * pointing/focusing the block. Writes to the preview-state store; the
 * canvas iframe wrapper consumes that store to inject a temporary
 * `data-ap-preview-state="..."` attribute on the selected block.
 *
 * The toggle is editor-only — saved content never sees the preview
 * attribute. Selecting `idle` clears the preview, restoring the
 * canvas to default-state styles.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { useSyncExternalStore } from 'react'
import { __, sprintf } from '@wordpress/i18n'

import {
	getPreviewState,
	setPreviewState,
	subscribePreviewState,
} from './active-state'
import { type StateRegistry, stateLabel } from './registry'
import { BASE_KEY } from './types'
import { TEXT_DOMAIN } from '../vendor/i18n'

export interface PreviewStateToggleProps {
	registry: StateRegistry
	className?: string
	/** Optional allow-list — defaults to every registered state. */
	allowedStates?: string[]
}

export function PreviewStateToggle( {
	registry,
	className,
	allowedStates,
}: PreviewStateToggleProps ): JSX.Element {
	const preview = useSyncExternalStore( subscribePreviewState, getPreviewState, getPreviewState )

	const states = registry
		.all()
		.filter( ( definition ) => BASE_KEY !== definition.key )
		.filter( ( definition ) => ! allowedStates || allowedStates.includes( definition.key ) )

	const handleSelect = ( key: string | null ): void => {
		setPreviewState( key )
	}

	return (
		<div className={ className ?? 've-preview-state-toggle' } role="group" aria-label={ __( 'Preview state', TEXT_DOMAIN ) }>
			<button
				type="button"
				aria-pressed={ null === preview }
				onClick={ () => handleSelect( null ) }
			>
				{ __( 'No preview', TEXT_DOMAIN ) }
			</button>

			{ states.map( ( definition ) => (
				<button
					key={ definition.key }
					type="button"
					aria-pressed={ definition.key === preview }
					data-state={ definition.key }
					onClick={ () => handleSelect( definition.key === preview ? null : definition.key ) }
					/* translators: %s: state label, e.g. "Hover". */
					title={ sprintf( __( 'Simulate the %s state on the canvas', TEXT_DOMAIN ), stateLabel( definition ) ) }
				>
					{ stateLabel( definition ) }
				</button>
			) ) }
		</div>
	)
}
