/**
 * Save deprecations for older shapes of the saved state CSS (M-5, F-9).
 *
 * `withStateStyles` writes the output of `emitStateCss()` into each
 * block's saved markup as `<style data-ap-state-scope>`. Changing that
 * output changes the markup, so content saved by an older release no
 * longer matches `save` and the parser flags it as invalid. That hits
 * every surface that parses stored HTML: the site editor, the pattern
 * editor and the pattern thumbnails.
 *
 * Two older shapes exist:
 *
 *  - **1.14 and earlier.** `var:preset|{kind}|{slug}` references were
 *    written through unchanged and there was no reduced-motion guard.
 *  - **1.15 development builds.** References were expanded (#875) but
 *    there was still no reduced-motion guard. 1.15.0 never shipped this
 *    shape; it only exists in databases that ran the release branch.
 *
 * A `blocks.registerBlockType` filter prepends one deprecation per
 * shape to every block that supports `artisanpackStates`. Each one is
 * the block's own `save`, `attributes`, `supports` and `apiVersion`,
 * plus a private supports flag that tells `wrapSaveElement` which shape
 * to emit. Attributes are unchanged between the shapes, so there is no
 * `migrate` (the parsed attributes are kept) and no `isEligible` (only
 * invalid blocks need the fallback). Re-saving the block writes the
 * current shape.
 *
 * Existing deprecations whose own `supports` opt into
 * `artisanpackStates` get the same pair of variants, so content saved
 * with an older `save` and an older state-CSS shape still validates.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

import { addFilter } from '@wordpress/hooks'

import type { EmitStateCssOptions } from './css-emitter'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/state-css-deprecations'

/**
 * Run after the other `blocks.registerBlockType` filters, so the
 * copied `attributes` and `supports` already carry everything they add.
 */
const FILTER_PRIORITY = 100

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.state-css-deprecations.registered',
)

/**
 * Private `supports` key that carries the emitter options a deprecation
 * saves with. It lives in `supports` because Gutenberg keeps only the
 * deprecation keys (`attributes`, `supports`, `save`, `migrate`,
 * `isEligible`, `apiVersion`) when it registers a deprecation.
 *
 * @since 1.15.0
 */
export const STATE_CSS_SHAPE_SUPPORT = '__artisanpackStateCssShape'

/**
 * The older saved shapes, newest first.
 *
 * @since 1.15.0
 */
export const LEGACY_STATE_CSS_SHAPES: ReadonlyArray<Readonly<EmitStateCssOptions>> = [
	// 1.15 development builds: presets expanded, no reduced-motion guard.
	{ expandPresets: true, reducedMotionGuard: false },
	// 1.14 and earlier: raw `var:preset|…`, no reduced-motion guard.
	{ expandPresets: false, reducedMotionGuard: false },
]

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface DeprecationLike {
	attributes?: Record<string, unknown>
	supports?: Record<string, unknown>
	save?: unknown
	migrate?: unknown
	isEligible?: unknown
	apiVersion?: number
	[key: string]: unknown
}

interface BlockSettingsLike extends DeprecationLike {
	deprecated?: DeprecationLike[]
}

function supportsStates( supports: Record<string, unknown> | undefined ): boolean {
	return Boolean( supports?.artisanpackStates )
}

function isLegacyShapeEntry( entry: DeprecationLike ): boolean {
	return Boolean( entry.supports?.[ STATE_CSS_SHAPE_SUPPORT ] )
}

/**
 * Read the state-CSS shape a (deprecated) block type saves with, or
 * `null` for the current shape.
 *
 * @since 1.15.0
 */
export function stateCssShapeFor(
	blockType: { supports?: Record<string, unknown> } | null | undefined,
): EmitStateCssOptions | null {
	const shape = blockType?.supports?.[ STATE_CSS_SHAPE_SUPPORT ]

	return shape && 'object' === typeof shape ? shape as EmitStateCssOptions : null
}

function variantsOf( entry: DeprecationLike ): DeprecationLike[] {
	return LEGACY_STATE_CSS_SHAPES.map( ( shape ) => ( {
		...entry,
		attributes: { ...( entry.attributes ?? {} ) },
		supports:   { ...( entry.supports ?? {} ), [ STATE_CSS_SHAPE_SUPPORT ]: { ...shape } },
	} ) )
}

/**
 * `blocks.registerBlockType` callback: prepend the legacy state-CSS
 * deprecations to a block that supports `artisanpackStates`.
 *
 * @since 1.15.0
 *
 * @param settings    Block settings being registered.
 * @param _name       Block name.
 * @param deprecation The deprecation being filtered, when Gutenberg
 *                    runs the filter over a deprecation entry.
 */
export function addStateCssDeprecations(
	settings: BlockSettingsLike,
	_name?: string,
	deprecation?: unknown,
): BlockSettingsLike {
	// Gutenberg also runs this filter over each deprecation entry.
	if ( deprecation || ! supportsStates( settings.supports ) || 'function' !== typeof settings.save ) {
		return settings
	}

	const existing = Array.isArray( settings.deprecated ) ? settings.deprecated : []

	// Idempotent: settings re-registered from `getBlockType()` already
	// carry the entries.
	if ( existing.some( isLegacyShapeEntry ) ) {
		return settings
	}

	const current: DeprecationLike = {
		attributes: settings.attributes,
		supports:   settings.supports,
		save:       settings.save,
	}

	if ( undefined !== settings.apiVersion ) {
		current.apiVersion = settings.apiVersion
	}

	const olderSaves = existing
		.filter( ( entry ) => supportsStates( entry.supports ) )
		.flatMap( variantsOf )

	return {
		...settings,
		deprecated: [ ...variantsOf( current ), ...olderSaves, ...existing ],
	}
}

/**
 * Register the filter at most once per page. Must run before blocks
 * register.
 *
 * @since 1.15.0
 */
export function registerStateCssDeprecations(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, addStateCssDeprecations, FILTER_PRIORITY )
	host[ REGISTERED_KEY ] = true
}
