/**
 * `blocks.registerBlockType` filter — add `style.position` to
 * `artisanpackResponsive.attributes` routing whenever a block declares
 * `supports.position` (#641).
 *
 * The block-level gate is `supports.position: true` OR any truthy
 * object at `supports.position` (Gutenberg's own sticky-enabled shape
 * `{ sticky: true }` counts). Explicit
 * `artisanpackResponsive: false` opt-out is preserved.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { addFilter } from '@wordpress/hooks'

import { POSITION_ATTRIBUTE_PATH } from './types'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/position-supports'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.position-supports.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface RoutingSupport {
	attributes?: string[]
	[key: string]: unknown
}

interface BlockSupports {
	position?: boolean | Record<string, unknown>
	artisanpackResponsive?: RoutingSupport | boolean
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: BlockSupports
	[key: string]: unknown
}

export function positionEnabled( supports: BlockSupports | undefined ): boolean {
	if ( ! supports ) {
		return false
	}

	const raw = supports.position

	if ( true === raw ) {
		return true
	}

	return Boolean( raw && 'object' === typeof raw )
}

function ensureRouted( supports: BlockSupports ): RoutingSupport | false {
	const raw = supports.artisanpackResponsive

	if ( false === raw ) {
		return false
	}

	if ( raw === undefined || raw === null || true === raw ) {
		return { attributes: [ POSITION_ATTRIBUTE_PATH ] }
	}

	if ( 'object' !== typeof raw ) {
		return { attributes: [ POSITION_ATTRIBUTE_PATH ] }
	}

	const attributes = Array.isArray( raw.attributes ) ? raw.attributes : []

	if ( attributes.includes( POSITION_ATTRIBUTE_PATH ) ) {
		return raw
	}

	return {
		...raw,
		attributes: [ ...attributes, POSITION_ATTRIBUTE_PATH ],
	}
}

function injectPositionSupports( settings: BlockSettingsLike ): BlockSettingsLike {
	if ( ! positionEnabled( settings.supports ) ) {
		return settings
	}

	const supports = settings.supports as BlockSupports

	return {
		...settings,
		supports: {
			...supports,
			artisanpackResponsive: ensureRouted( supports ),
		},
	}
}

/**
 * Register the filter at most once per page. Idempotent — safe to call
 * from both the post-editor and site-editor entries.
 */
export function registerPositionSupportsExtension(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectPositionSupports )
	host[ REGISTERED_KEY ] = true
}
