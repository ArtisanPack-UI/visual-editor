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

export const DEFAULT_TRANSITION = 'all 150ms ease'

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

function stringifyValue( value: unknown, config: PathConfig ): string | null {
	if ( null === value || undefined === value ) {
		return null
	}

	if ( 'number' === typeof value ) {
		return String( value )
	}

	if ( 'string' !== typeof value || '' === value ) {
		return null
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

function bucketByState( states: StatesByPath, registry: StateRegistry ): BucketsByState {
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
			const value = stringifyValue( overrides[ stateKey ], config )
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
 * Emit a CSS string for one block scope, given its full
 * `{ path -> stateful-value }` map.
 *
 * Returns an empty string when nothing's worth emitting (no overrides
 * stored, or the scope is blank). Callers should treat empty as a
 * signal to skip injecting the `<style>` tag entirely.
 */
export function emitStateCss(
	scope: string,
	states: StatesByPath | null | undefined,
	registry: StateRegistry,
): string {
	if ( '' === scope.trim() || ! states ) {
		return ''
	}

	const buckets = bucketByState( states, registry )

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

		if ( definition.hoverMediaWrap ) {
			hoverBlock += ( '' === hoverBlock ? '' : ' ' ) + rule
			continue
		}

		parts.push( rule )
	}

	if ( '' !== hoverBlock ) {
		parts.push( `@media (hover: hover) { ${ hoverBlock } }` )
	}

	return parts.join( ' ' )
}
