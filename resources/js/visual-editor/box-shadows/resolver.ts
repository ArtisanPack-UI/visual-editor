/**
 * Resolver — walks a block's attribute tree and normalises the box-
 * shadow payload (#607).
 *
 * Mirrors PHP `BoxShadowResolver::resolve` exactly so the editor
 * preview and server render produce byte-identical CSS:
 *
 * - Idle value lives at `style.shadow` (a structured subtree).
 * - Per-state overrides ride `attributes.states['style.shadow']`
 *   (canonical) or `['shadow']` (shorthand).
 * - Per-breakpoint overrides ride `attributes.responsive['style.shadow']`.
 *
 * A `preset` slug short-circuits everything else — when set, the
 * resolver returns a layer whose `preset` field carries the slug,
 * and the emitter renders `box-shadow: var(--wp--preset--shadow--{slug})`.
 *
 * The `gradient` field accepts both slugs (expanded to
 * `var(--wp--preset--gradient--{slug})`) and raw CSS gradient values.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import type {
	BoxShadowAttributes,
	ResolvedBoxShadow,
	ResolvedShadowLayer,
	ShadowSubtree,
} from './types'

const SLUG_RE      = /^[a-z0-9][a-z0-9_-]*$/i
const SHADOW_PATHS = [ 'style.shadow', 'shadow' ]

function expandGradient( value: unknown ): string | null {
	if ( 'string' !== typeof value ) {
		return null
	}

	const trimmed = value.trim()

	if ( '' === trimmed ) {
		return null
	}

	if ( SLUG_RE.test( trimmed ) ) {
		return `var(--wp--preset--gradient--${ trimmed })`
	}

	return trimmed
}

function trimmedString( value: unknown ): string | null {
	if ( 'string' !== typeof value ) {
		return null
	}

	const trimmed = value.trim()

	return '' === trimmed ? null : trimmed
}

function expandPreset( value: unknown ): string | null {
	const slug = trimmedString( value )

	if ( null === slug || ! SLUG_RE.test( slug ) ) {
		return null
	}

	return slug
}

function resolveLayer( subtree: ShadowSubtree | null | undefined ): ResolvedShadowLayer | null {
	if ( ! subtree || 'object' !== typeof subtree ) {
		return null
	}

	const preset = expandPreset( subtree.preset )

	// Defaults are `0px`, not `0`. Safari (and the CSS spec) require
	// a unit inside `calc()` — `calc(-1 * 0)` is invalid because `0`
	// is a `<number>`, not a `<length>`. The unitless form works in
	// stock `box-shadow` declarations (the property accepts `0` as
	// a length shorthand) but breaks the gradient pseudo's calc()
	// sizing.
	const offsetX  = trimmedString( subtree.offsetX ) ?? '0px'
	const offsetY  = trimmedString( subtree.offsetY ) ?? '0px'
	const blur     = trimmedString( subtree.blur ) ?? '0px'
	const spread   = trimmedString( subtree.spread ) ?? '0px'
	const color    = trimmedString( subtree.color )
	const gradient = expandGradient( subtree.gradient )
	const inset    = true === subtree.inset

	// A layer is meaningful when it carries a preset OR any of the
	// structured fields. Empty subtrees (all fields null) are dropped
	// so callers can `null`-check the return value and skip emission.
	const hasStructured =
		null !== trimmedString( subtree.offsetX )
		|| null !== trimmedString( subtree.offsetY )
		|| null !== trimmedString( subtree.blur )
		|| null !== trimmedString( subtree.spread )
		|| null !== color
		|| null !== gradient
		|| true === subtree.inset

	if ( null === preset && ! hasStructured ) {
		return null
	}

	return {
		offsetX,
		offsetY,
		blur,
		spread,
		color,
		gradient,
		inset,
		preset,
	}
}

function collectOverrides(
	bag: Record<string, Record<string, ShadowSubtree | null>> | null | undefined,
): Record<string, ResolvedShadowLayer> {
	if ( ! bag || 'object' !== typeof bag ) {
		return {}
	}

	const out: Record<string, ResolvedShadowLayer> = {}

	// Iterate in reverse-precedence so the canonical path overwrites
	// the shorthand; last-write-wins on assignment.
	for ( const path of [ ...SHADOW_PATHS ].reverse() ) {
		const overrides = bag[ path ]

		if ( ! overrides || 'object' !== typeof overrides ) {
			continue
		}

		for ( const key of Object.keys( overrides ) ) {
			if ( '' === key ) {
				continue
			}

			const layer = resolveLayer( overrides[ key ] )

			if ( null === layer ) {
				continue
			}

			out[ key ] = layer
		}
	}

	return out
}

/**
 * Resolve a block's attribute tree into the normalised payload the
 * emitter consumes. Returns `null` when no shadow configuration
 * exists at any cascade level — callers should skip emission entirely
 * in that case.
 */
export function resolveBoxShadow(
	attributes: BoxShadowAttributes | null | undefined,
): ResolvedBoxShadow | null {
	if ( ! attributes || 'object' !== typeof attributes ) {
		return null
	}

	const shadow      = attributes.style?.shadow ?? null
	const idle        = resolveLayer( shadow )
	const states      = collectOverrides( attributes.states )
	const breakpoints = collectOverrides( attributes.responsive )

	if (
		null === idle
		&& 0 === Object.keys( states ).length
		&& 0 === Object.keys( breakpoints ).length
	) {
		return null
	}

	return {
		idle,
		states,
		breakpoints,
	}
}

/**
 * Pluck every shadow preset slug AND gradient slug referenced
 * anywhere in a block's attribute tree (idle + per-state + per-
 * breakpoint). Used by the editor's token-warning surface to flag
 * references whose theme token was renamed or removed.
 *
 * Returns `{ shadows: string[], gradients: string[] }` so the
 * inspector can compare each list against its respective theme
 * settings bucket.
 */
export function referencedSlugs(
	attributes: BoxShadowAttributes | null | undefined,
): { shadows: string[]; gradients: string[] } {
	if ( ! attributes || 'object' !== typeof attributes ) {
		return { shadows: [], gradients: [] }
	}

	const shadows:   string[] = []
	const gradients: string[] = []

	collectSubtreeSlugs( shadows, gradients, attributes.style?.shadow )
	collectSlugsFromBag( shadows, gradients, attributes.states )
	collectSlugsFromBag( shadows, gradients, attributes.responsive )

	return {
		shadows:   Array.from( new Set( shadows ) ),
		gradients: Array.from( new Set( gradients ) ),
	}
}

function collectSubtreeSlugs(
	shadows: string[],
	gradients: string[],
	subtree: ShadowSubtree | null | undefined,
): void {
	if ( ! subtree || 'object' !== typeof subtree ) {
		return
	}

	const preset = expandPreset( subtree.preset )
	if ( null !== preset ) {
		shadows.push( preset )
	}

	if ( 'string' === typeof subtree.gradient ) {
		const trimmed = subtree.gradient.trim()
		if ( '' !== trimmed && SLUG_RE.test( trimmed ) ) {
			gradients.push( trimmed )
		}
	}
}

function collectSlugsFromBag(
	shadows: string[],
	gradients: string[],
	bag: Record<string, Record<string, ShadowSubtree | null>> | null | undefined,
): void {
	if ( ! bag || 'object' !== typeof bag ) {
		return
	}

	for ( const path of SHADOW_PATHS ) {
		const overrides = bag[ path ]

		if ( ! overrides || 'object' !== typeof overrides ) {
			continue
		}

		for ( const key of Object.keys( overrides ) ) {
			collectSubtreeSlugs( shadows, gradients, overrides[ key ] )
		}
	}
}
