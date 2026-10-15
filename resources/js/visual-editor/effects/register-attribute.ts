/**
 * Inject the `artisanpackEffects` attribute on every block (#881).
 *
 * Effects apply to any block wrapper, so the attribute is added to every
 * block type unless it opts out with `supports.artisanpackEffects: false`
 * in `block.json`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

import { addFilter } from '@wordpress/hooks'

import { EFFECTS_ATTRIBUTE } from './compiler'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/effects-attribute'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.effects-attribute.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockSettingsLike {
	supports?: Record<string, unknown>
	attributes?: Record<string, unknown>
	[key: string]: unknown
}

/**
 * Whether a block type takes effects.
 *
 * @since 1.15.0
 */
export function effectsEnabled( settings: BlockSettingsLike | undefined | null ): boolean {
	return false !== settings?.supports?.artisanpackEffects
}

function injectEffectsAttribute( settings: BlockSettingsLike ): BlockSettingsLike {
	if ( ! effectsEnabled( settings ) ) {
		return settings
	}

	if ( settings.attributes && EFFECTS_ATTRIBUTE in settings.attributes ) {
		return settings
	}

	return {
		...settings,
		attributes: {
			...( settings.attributes ?? {} ),
			[ EFFECTS_ATTRIBUTE ]: {
				type: 'object',
			},
		},
	}
}

/**
 * Idempotent — safe to call from both editor bootstraps.
 *
 * @since 1.15.0
 */
export function registerEffectsAttribute(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectEffectsAttribute )
	host[ REGISTERED_KEY ] = true
}
