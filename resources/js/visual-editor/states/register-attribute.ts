/**
 * Inject the `states` attribute on every block that opts into
 * per-state styling (#488).
 *
 * Blocks declare opt-in via `supports.artisanpackStates` in
 * `block.json`. This `blocks.registerBlockType` filter adds the
 * storage attribute at registration time so individual `block.json`
 * files don't need to declare it by hand.
 *
 * The injected attribute is a plain `object` keyed by attribute path
 * (e.g. `style.color.background`) → discriminated
 * `{ idle, hover, focus, … }` object. Reads/writes are mediated by
 * `withStateAttributes`; this file just makes sure the attribute
 * exists so `setAttributes` calls actually persist.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/states-attribute'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.states-attribute.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockSupports {
	artisanpackStates?: {
		attributes?: string[]
		states?: string[]
	} | boolean
}

interface BlockSettingsLike {
	supports?: BlockSupports
	attributes?: Record<string, unknown>
	[key: string]: unknown
}

function injectStateAttribute( settings: BlockSettingsLike ): BlockSettingsLike {
	const support = settings.supports?.artisanpackStates

	if ( ! support ) {
		return settings
	}

	if ( settings.attributes && 'states' in settings.attributes ) {
		return settings
	}

	return {
		...settings,
		attributes: {
			...( settings.attributes ?? {} ),
			states: {
				type:    'object',
				default: null,
			},
		},
	}
}

/**
 * Register the filter at most once per page. Idempotent — safe to
 * call from both the post-editor and site-editor entries.
 */
export function registerStateAttribute(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectStateAttribute )
	host[ REGISTERED_KEY ] = true
}
