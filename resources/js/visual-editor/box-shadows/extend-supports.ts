/**
 * `blocks.registerBlockType` filter — auto-add `style.shadow` to the
 * `artisanpackStates.attributes` and `artisanpackResponsive.attributes`
 * routing lists for every block that opts into border support (#607).
 *
 * Per #607, the gating reads existing border support directly rather
 * than a sub-flag — every block with any `__experimentalBorder` (or
 * `border`) support gets the shadow panel + cascade routing. This
 * means ~94 blocks pick up shadow controls with NO block.json
 * changes.
 *
 * Explicit `artisanpackStates: false` / `artisanpackResponsive: false`
 * opt-outs are preserved.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import { addFilter } from '@wordpress/hooks'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/box-shadow-supports'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.box-shadow-supports.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BorderSupport {
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
	shadow?: boolean | Record<string, unknown>
	[key: string]: unknown
}

interface BlockSettingsLike {
	supports?: BlockSupports
	[key: string]: unknown
}

const ROUTING_PATH = 'style.shadow'

function shadowEnabled( supports: BlockSupports | undefined ): boolean {
	if ( ! supports ) {
		return false
	}

	const border = supports.__experimentalBorder ?? supports.border

	return Boolean( border && 'object' === typeof border )
}

function ensureRouted(
	supports: BlockSupports,
	key: 'artisanpackStates' | 'artisanpackResponsive',
): RoutingSupport | false {
	const raw = supports[ key ]

	// Explicit opt-out — preserve a deliberate `false` declaration in
	// the block.json.
	if ( false === raw ) {
		return false
	}

	if ( raw === undefined || raw === null || true === raw ) {
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

function injectBoxShadowSupports(
	settings: BlockSettingsLike,
): BlockSettingsLike {
	if ( ! shadowEnabled( settings.supports ) ) {
		return settings
	}

	const supports = settings.supports as BlockSupports

	// IMPORTANT: strip the native WordPress `supports.shadow`. Core's
	// shadow support uses the SAME attribute path (`style.shadow`) we
	// use, but stores a CSS string (`var(--wp--preset--shadow--{slug})`)
	// rather than the structured `{offsetX, offsetY, blur, spread,
	// color, gradient, inset, preset}` object our panel writes. Left
	// enabled, both panels render in the inspector and they fight over
	// the attribute — the native compiler treats our object as garbage
	// and the save filter's `readScopeId` returns null because a string
	// has no `_shadowScopeId` property. So the saved markup carries
	// neither rule and the shadow never renders. Removing the native
	// support hides the native panel and frees `style.shadow` for our
	// structured shape — ours becomes the single source of truth.
	const nextSupports: BlockSupports = {
		...supports,
		shadow:                false,
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
export function registerBoxShadowSupportsExtension(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, injectBoxShadowSupports )
	host[ REGISTERED_KEY ] = true
}
