/**
 * `blocks.registerBlockType` filter — auto-add `border.gradient` to the
 * `artisanpackStates.attributes` and `artisanpackResponsive.attributes`
 * routing lists when a block opts into
 * `supports.__experimentalBorder.gradient: true` (#490).
 *
 * Saves every block's `block.json` from having to repeat the
 * `border.gradient` path in both lists by hand. Without this filter the
 * generic state/responsive HOCs would treat gradient writes as
 * non-routable and fall through to the base idle bag — silently
 * losing the per-state/per-breakpoint cascade the inspector chip
 * promised.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { addFilter } from '@wordpress/hooks'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/gradient-border-supports'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.gradient-border-supports.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BorderSupport {
	gradient?: boolean
	[key: string]: unknown
}

interface RoutingSupport {
	attributes?: string[]
	[key: string]: unknown
}

interface BlockSupports {
	border?: BorderSupport
	__experimentalBorder?: BorderSupport
	artisanpackStates?: RoutingSupport | boolean
	artisanpackResponsive?: RoutingSupport | boolean
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: BlockSupports
	[key: string]: unknown
}

const ROUTING_PATH = 'border.gradient'

function gradientEnabled( supports: BlockSupports | undefined ): boolean {
	if ( ! supports ) {
		return false
	}

	const border = supports.__experimentalBorder ?? supports.border

	if ( ! border || 'object' !== typeof border ) {
		return false
	}

	return true === border.gradient
}

function ensureRouted(
	supports: BlockSupports,
	key: 'artisanpackStates' | 'artisanpackResponsive',
): RoutingSupport {
	const raw = supports[ key ]

	if ( ! raw || 'boolean' === typeof raw ) {
		return { attributes: [ ROUTING_PATH ] }
	}

	if ( 'object' !== typeof raw ) {
		return { attributes: [ ROUTING_PATH ] }
	}

	const attributes = Array.isArray( raw.attributes ) ? raw.attributes : []

	if ( attributes.includes( ROUTING_PATH ) ) {
		return raw
	}

	return {
		...raw,
		attributes: [ ...attributes, ROUTING_PATH ],
	}
}

function injectGradientBorderSupports(
	settings: BlockSettingsLike,
): BlockSettingsLike {
	if ( ! gradientEnabled( settings.supports ) ) {
		return settings
	}

	const supports = settings.supports as BlockSupports

	// IMPORTANT: we deliberately do NOT disable `__experimentalBorder.color`.
	// Gutenberg's `BorderBoxControl` bundles color + width + style + the
	// popover into one component — there's no flag to disable the color
	// slot while keeping style. Flipping `color: false` either removes
	// the whole BorderBoxControl (when width and style are also off) or
	// has no effect on the color popover content (the picker is rendered
	// unconditionally inside `BorderControlDropdown`'s popover regardless
	// of the supports.color flag). So we leave the native picker as-is;
	// our gradient picker is a sibling, not a replacement. When BOTH a
	// solid color and a gradient are set, the gradient wins visually via
	// `border-color: transparent !important` in `GradientBorderEmitter`.
	const nextSupports: BlockSupports = {
		...supports,
		artisanpackStates:     ensureRouted( supports, 'artisanpackStates' ),
		artisanpackResponsive: ensureRouted( supports, 'artisanpackResponsive' ),
	}

	return {
		...settings,
		supports: nextSupports,
	}
}

/**
 * Register the filter at most once per page. Idempotent — safe to
 * call from both the post-editor and site-editor entries.
 */
export function registerGradientBorderSupportsExtension(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectGradientBorderSupports )
	host[ REGISTERED_KEY ] = true
}
