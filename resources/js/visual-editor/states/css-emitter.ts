/**
 * Client-side CSS emitter (#488).
 *
 * Mirrors PHP `StateCssEmitter`. Given a `{ path -> stateful-value }`
 * map and a scope class, produces the CSS rules the editor canvas
 * and saved markup ship with the block.
 *
 *  - Idle rule emitted against the scope.
 *  - Non-idle states emit a rule only when their value differs from
 *    the inheritance parent.
 *  - Rules whose state has `hoverMediaWrap = true` get wrapped in
 *    `@media (hover: hover)` so touch devices don't sticky-state on
 *    tap.
 *  - When any non-idle override is set and no explicit transition is
 *    authored, a default `transition: all 150ms ease;` is added to
 *    the idle rule.
 *  - A trailing `@media (prefers-reduced-motion: reduce)` block turns
 *    the transitions off and holds every non-idle state's `transform`
 *    at its idle value, so reduced-motion users get no animated or
 *    moving state change (F-9). Mirrors PHP `StateCssEmitter`.
 *
 * The path → CSS property mapping covers every attribute on the
 * v1.0 supported list. Unknown paths are skipped silently so a
 * mistyped opt-in root in a theme override doesn't blow up the
 * canvas.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.0.0
 */

import type { StateRegistry } from './registry'
import { distinctStateOverrides } from './resolver'
import { BASE_KEY, type StatefulAttribute } from './types'
import { presetSlug } from '../support/preset-slug'

export const DEFAULT_TRANSITION = 'all 150ms ease'

/**
 * Options that select an older shape of the emitted CSS.
 *
 * Saved markup carries the emitted CSS, so a block saved by an older
 * release only validates against the output that release produced.
 * The state-CSS save deprecations (see `state-css-deprecations.ts`)
 * use these to reproduce those shapes.
 *
 * @since 1.15.0
 */
export interface EmitStateCssOptions {
	/**
	 * Expand `var:preset|{kind}|{slug}` references into
	 * `var(--wp--preset--{kind}--{slug})` (#875). Releases before
	 * 1.15.0 wrote them through unchanged. Default `true`.
	 */
	expandPresets?: boolean

	/**
	 * Append the `prefers-reduced-motion: reduce` guard (F-9).
	 * Releases before 1.15.0 emitted none. Default `true`.
	 */
	reducedMotionGuard?: boolean

	/**
	 * Slug expanded preset segments the way 1.15 development builds
	 * did: Unicode `toLowerCase()` and one `-` per UTF-16 unit. 1.15.0
	 * slugs byte-wise like PHP (R-19), which differs only for slugs
	 * with non-ASCII characters (`İnk`: `i-nk` before, `--nk` now).
	 * Only matters with `expandPresets`. Default `false`.
	 */
	legacyPresetSlugs?: boolean
}

/**
 * Per-path config: which CSS property the path emits as, plus an
 * optional preset-token slug used when the editor wrote a palette slug
 * rather than a raw CSS value. Paths that aren't in here are silently
 * dropped — most v1.0 supported attributes live under `style.*`, and
 * the renderer normalises both shapes before lookup.
 *
 * The `preset` field mirrors WordPress's `--wp--preset--{kind}--{slug}`
 * custom-property naming: when a block's `backgroundColor` attribute
 * is `'vivid-purple'`, the emitter outputs
 * `background-color: var(--wp--preset--color--vivid-purple);`.
 */
interface PathConfig {
	property: string
	preset?: 'color' | 'gradient'
}

const PATH_TO_CONFIG: Record<string, PathConfig> = {
	// Top-level palette-slug shortcuts WordPress writes when a user
	// picks from the palette (vs. authoring a custom hex). These must
	// be routed in lockstep with the `style.color.*` paths below or
	// state writes silently fall through to the base.
	'backgroundColor':            { property: 'background-color', preset: 'color' },
	'textColor':                  { property: 'color',            preset: 'color' },
	'gradient':                   { property: 'background',       preset: 'gradient' },

	// Style-bag shapes the inspector writes when a user authors a
	// custom value via the color picker / border panel / etc.
	'color.background':           { property: 'background-color' },
	'color.text':                 { property: 'color' },
	'color.gradient':             { property: 'background' },
	'border.color':               { property: 'border-color' },
	'border.width':               { property: 'border-width' },
	'border.style':               { property: 'border-style' },
	'border.radius':              { property: 'border-radius' },
	'shadow':                     { property: 'box-shadow' },
	'typography.textDecoration':  { property: 'text-decoration' },
	'dimensions.transform':       { property: 'transform' },
	'transition':                 { property: 'transition' },
}

/**
 * Strip a leading `style.` so callers can pass either
 * `style.color.background` or `color.background` interchangeably.
 */
function normalisePath( path: string ): string {
	return path.startsWith( 'style.' ) ? path.slice( 'style.'.length ) : path
}

function configFor( path: string ): PathConfig | null {
	const normalised = normalisePath( path )
	return PATH_TO_CONFIG[ normalised ] ?? null
}

// Palette slugs are kebab/alphanum identifiers; anything containing
// CSS punctuation (`#`, `(`, `,`, etc.) is already a raw CSS value
// and rides through unchanged. Keeps `var(--my-token)`, `#ff0000`,
// `rgb(…)`, and `linear-gradient(…)` from being double-wrapped.
const SLUG_RE = /^[a-z0-9][a-z0-9_-]*$/i

const PRESET_REFERENCE_PREFIX = 'var:preset|'

/**
 * Expand Gutenberg's `var:preset|{kind}|{slug}` shorthand — what a
 * palette swatch writes into `color.background`, `border.color`,
 * `shadow`, … — into `var(--wp--preset--{kind}--{slug})` (#875). Same
 * segment rule as `BlockSupports::presetSlug()` on the PHP side (see
 * {@link presetSlug}: byte-wise, ASCII-only lowercase, R-19).
 */
function expandPresetReference( value: string, legacySlugs = false ): string {
	const segments = value
		.slice( PRESET_REFERENCE_PREFIX.length )
		.split( '|' )
		.map( legacySlugs ? legacyPresetSlug : presetSlug )

	return `var(--wp--preset--${ segments.join( '--' ) })`
}

/**
 * The segment rule 1.15 development builds saved with (Unicode
 * lowercase, one `-` per UTF-16 unit). Kept only so the state-CSS save
 * deprecation can reproduce that markup.
 */
function legacyPresetSlug( segment: string ): string {
	return segment
		.replace( /([a-z])([A-Z])/g, '$1-$2' )
		.toLowerCase()
		.replace( /[^a-z0-9-]/g, '-' )
}

function stringifyValue( value: unknown, config: PathConfig, expandPresets: boolean, legacySlugs = false ): string | null {
	if ( null === value || undefined === value ) {
		return null
	}

	if ( 'number' === typeof value ) {
		return String( value )
	}

	if ( 'string' !== typeof value || '' === value ) {
		return null
	}

	if ( expandPresets && value.startsWith( PRESET_REFERENCE_PREFIX ) ) {
		return expandPresetReference( value, legacySlugs )
	}

	if ( config.preset && SLUG_RE.test( value ) ) {
		return `var(--wp--preset--${ config.preset }--${ value })`
	}

	return value
}

export type StatesByPath = Record<string, StatefulAttribute<unknown> | null | undefined>

interface BucketsByState {
	[stateKey: string]: Record<string, string>
}

function bucketByState( states: StatesByPath, registry: StateRegistry, expandPresets: boolean, legacySlugs = false ): BucketsByState {
	const buckets: BucketsByState = {}

	for ( const path of Object.keys( states ) ) {
		const config = configFor( path )
		if ( null === config ) {
			continue
		}

		const stateful = states[ path ]
		if ( null === stateful || undefined === stateful ) {
			continue
		}

		const overrides = distinctStateOverrides( stateful, registry )

		for ( const stateKey of Object.keys( overrides ) ) {
			const value = stringifyValue( overrides[ stateKey ], config, expandPresets, legacySlugs )
			if ( null === value ) {
				continue
			}

			buckets[ stateKey ]                    = buckets[ stateKey ] ?? {}
			buckets[ stateKey ][ config.property ] = value
		}
	}

	return buckets
}

// Properties that should NOT carry `!important` — `transition` is the
// only one in the v1.0 set; an `!important` transition can't be
// disabled by host CSS, which is rarely what you want.
const NEVER_IMPORTANT = new Set( [ 'transition' ] )

function joinDeclarations( declarations: Record<string, string> ): string {
	return Object.keys( declarations )
		.map( ( property ) => {
			const value     = declarations[ property ]
			const important = NEVER_IMPORTANT.has( property ) ? '' : ' !important'
			return `${ property }: ${ value }${ important };`
		} )
		.join( ' ' )
}

// Common interactive descendants the emitter mirrors *state* rules
// onto, so a scope class on the block wrapper still styles the actual
// link/button inside (e.g. `wp-block-button` renders its visual
// element as a child `wp-block-button__link`). Mirroring is only
// applied to non-idle pseudo-states — idle styles fall through the
// scope alone so they don't override the descendant's own base
// colors (which would surprise a cover/media-text block whose idle
// background should NOT also paint nested links).
const DESCENDANT_TARGETS = 'a, button'

function selectorFor( scope: string, selector: string ): string {
	if ( '' === selector ) {
		// Idle base — emit against the scope only; the WordPress
		// `has-{slug}-*` utility classes plus the block's own root
		// selector handle nested children. Mirroring here would leak
		// the block-level idle color into every descendant link.
		return scope
	}

	const pieces = selector.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean )
	const mapped: string[] = []

	for ( const piece of pieces ) {
		const resolved = piece.includes( '&' )
			? piece.replaceAll( '&', scope )
			: `${ scope }${ piece }`

		mapped.push( resolved )

		// Mirror the pseudo-state onto interactive descendants. For
		// `.ap-state-XXX:hover`, also emit
		// `.ap-state-XXX:hover :is(a, button)` so the inner button link
		// picks up the hover styles even though it's hovered as a
		// child of the wrapper.
		mapped.push( `${ resolved } :is(${ DESCENDANT_TARGETS })` )
	}

	return mapped.join( ', ' )
}

/**
 * Build the `prefers-reduced-motion: reduce` guard (F-9).
 *
 * - The idle rule's transition (authored or the default) becomes
 *   `none`. It is `!important`, so it also beats a non-idle state's
 *   own (never-important) `transition` on the block itself.
 * - Each non-idle rule that sets a `transition` or `transform` gets a
 *   twin on the same selector (descendant mirror included) that turns
 *   the transition off and holds the transform at its idle value, so
 *   the element does not move on hover or focus.
 *
 * Returns an empty string when nothing in the output can animate or
 * move.
 */
function reducedMotionGuard(
	scope: string,
	idleDeclarations: Record<string, string>,
	stateRules: Array<{ selector: string; declarations: Record<string, string> }>,
): string {
	const rules: string[] = []

	if ( 'transition' in idleDeclarations ) {
		rules.push( `${ selectorFor( scope, '' ) } { transition: none !important; }` )
	}

	for ( const { selector, declarations } of stateRules ) {
		const neutral: string[] = []

		if ( 'transition' in declarations ) {
			neutral.push( 'transition: none !important;' )
		}

		if ( 'transform' in declarations ) {
			neutral.push( `transform: ${ idleDeclarations.transform ?? 'none' } !important;` )
		}

		if ( 0 < neutral.length ) {
			rules.push( `${ selector } { ${ neutral.join( ' ' ) } }` )
		}
	}

	if ( 0 === rules.length ) {
		return ''
	}

	return `@media (prefers-reduced-motion: reduce) { ${ rules.join( ' ' ) } }`
}

/**
 * Emit a CSS string for one block scope, given its full
 * `{ path -> stateful-value }` map.
 *
 * Returns an empty string when nothing's worth emitting (no overrides
 * stored, or the scope is blank). Callers should treat empty as a
 * signal to skip injecting the `<style>` tag entirely.
 *
 * @since 1.0.0
 * @since 1.15.0 Expands `var:preset|…` references, appends the
 *               reduced-motion guard, and accepts `options` to emit
 *               the older shapes instead.
 */
export function emitStateCss(
	scope: string,
	states: StatesByPath | null | undefined,
	registry: StateRegistry,
	options: EmitStateCssOptions = {},
): string {
	if ( '' === scope.trim() || ! states ) {
		return ''
	}

	const {
		expandPresets = true,
		reducedMotionGuard: withGuard = true,
		legacyPresetSlugs = false,
	} = options

	const buckets = bucketByState( states, registry, expandPresets, legacyPresetSlugs )

	if ( 0 === Object.keys( buckets ).length ) {
		return ''
	}

	const idleDeclarations: Record<string, string> = { ...( buckets[ BASE_KEY ] ?? {} ) }

	const hasNonIdleOverride = registry
		.keys()
		.some( ( key ) => BASE_KEY !== key && buckets[ key ] && 0 < Object.keys( buckets[ key ] ).length )

	if ( hasNonIdleOverride && ! ( 'transition' in idleDeclarations ) ) {
		idleDeclarations.transition = DEFAULT_TRANSITION
	}

	const parts: string[] = []

	if ( 0 < Object.keys( idleDeclarations ).length ) {
		const idleSelector = selectorFor( scope, '' )
		parts.push( `${ idleSelector } { ${ joinDeclarations( idleDeclarations ) } }` )
	}

	let hoverBlock = ''

	const stateRules: Array<{ selector: string; declarations: Record<string, string> }> = []

	for ( const stateKey of registry.keys() ) {
		if ( BASE_KEY === stateKey ) {
			continue
		}

		const declarations = buckets[ stateKey ]
		if ( ! declarations || 0 === Object.keys( declarations ).length ) {
			continue
		}

		const definition = registry.get( stateKey )
		if ( ! definition ) {
			continue
		}

		const selector = selectorFor( scope, definition.selector )
		if ( '' === selector ) {
			continue
		}

		const rule = `${ selector } { ${ joinDeclarations( declarations ) } }`

		stateRules.push( { selector, declarations } )

		if ( definition.hoverMediaWrap ) {
			hoverBlock += ( '' === hoverBlock ? '' : ' ' ) + rule
			continue
		}

		parts.push( rule )
	}

	if ( '' !== hoverBlock ) {
		parts.push( `@media (hover: hover) { ${ hoverBlock } }` )
	}

	const guard = withGuard ? reducedMotionGuard( scope, idleDeclarations, stateRules ) : ''

	if ( '' !== guard ) {
		parts.push( guard )
	}

	return parts.join( ' ' )
}
