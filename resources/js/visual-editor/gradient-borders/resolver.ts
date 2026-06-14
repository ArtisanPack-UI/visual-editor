/**
 * Resolver — walks a block's attribute tree and normalises the gradient
 * border payload.
 *
 * Mirrors PHP `GradientBorderResolver::resolve` exactly so the editor
 * preview and server render produce byte-identical CSS:
 *
 * - Idle value lives at `style.border.gradient` (string slug or raw CSS).
 * - Per-state overrides ride `attributes.states['style.border.gradient']`
 *   (canonical) or `['border.gradient']` (shorthand).
 * - Per-breakpoint overrides ride `attributes.responsive['style.border.gradient']`.
 *
 * Slug values (`primary-glow`) become
 * `var(--wp--preset--gradient--primary-glow)`. Raw CSS values pass
 * through. Empty / null / non-string values are stripped.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import type {
	GradientBorderAttributes,
	ResolvedGradientBorder,
} from './types'

const SLUG_RE        = /^[a-z0-9][a-z0-9_-]*$/i
const GRADIENT_PATHS = [ 'style.border.gradient', 'border.gradient' ]

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

function collectOverrides(
	bag: Record<string, Record<string, string | null>> | null | undefined,
): Record<string, string> {
	if ( ! bag || 'object' !== typeof bag ) {
		return {}
	}

	const out: Record<string, string> = {}

	// Iterate in reverse-precedence so the canonical path overwrites
	// the shorthand; last-write-wins on assignment.
	for ( const path of [ ...GRADIENT_PATHS ].reverse() ) {
		const overrides = bag[ path ]

		if ( ! overrides || 'object' !== typeof overrides ) {
			continue
		}

		for ( const key of Object.keys( overrides ) ) {
			if ( '' === key ) {
				continue
			}

			const expanded = expandGradient( overrides[ key ] )

			if ( null === expanded ) {
				continue
			}

			out[ key ] = expanded
		}
	}

	return out
}

/**
 * Resolve a block's attribute tree into the normalised payload the
 * emitter consumes. Returns `null` when no gradient configuration
 * exists at any cascade level — callers should skip emission entirely
 * in that case.
 */
export function resolveGradientBorder(
	attributes: GradientBorderAttributes | null | undefined,
): ResolvedGradientBorder | null {
	if ( ! attributes || 'object' !== typeof attributes ) {
		return null
	}

	const border      = attributes.style?.border ?? null
	const idle        = expandGradient( border?.gradient )
	const states      = collectOverrides( attributes.states )
	const breakpoints = collectOverrides( attributes.responsive )

	if (
		null === idle
		&& 0 === Object.keys( states ).length
		&& 0 === Object.keys( breakpoints ).length
	) {
		return null
	}

	const width =
		border && 'string' === typeof border.width && '' !== border.width.trim()
			? border.width.trim()
			: null

	const radius = border
		? ( 'string' === typeof border.radius
			? border.radius
			: ( border.radius && 'object' === typeof border.radius
				? ( border.radius as Record<string, unknown> )
				: null ) )
		: null

	return {
		idle,
		states,
		breakpoints,
		width,
		radius,
	}
}

/**
 * Pluck every gradient slug referenced anywhere in a block's attribute
 * tree (idle + per-state + per-breakpoint). Used by the editor's token-
 * warning surface to flag references whose theme token was renamed or
 * removed.
 */
export function referencedSlugs(
	attributes: GradientBorderAttributes | null | undefined,
): string[] {
	if ( ! attributes || 'object' !== typeof attributes ) {
		return []
	}

	const slugs: string[] = []

	collectSlug( slugs, attributes.style?.border?.gradient )
	collectSlugsFromBag( slugs, attributes.states )
	collectSlugsFromBag( slugs, attributes.responsive )

	return Array.from( new Set( slugs ) )
}

function collectSlug( slugs: string[], value: unknown ): void {
	if ( 'string' !== typeof value ) {
		return
	}

	const trimmed = value.trim()

	if ( '' === trimmed || ! SLUG_RE.test( trimmed ) ) {
		return
	}

	slugs.push( trimmed )
}

function collectSlugsFromBag(
	slugs: string[],
	bag: Record<string, Record<string, string | null>> | null | undefined,
): void {
	if ( ! bag || 'object' !== typeof bag ) {
		return
	}

	for ( const path of GRADIENT_PATHS ) {
		const overrides = bag[ path ]

		if ( ! overrides || 'object' !== typeof overrides ) {
			continue
		}

		for ( const key of Object.keys( overrides ) ) {
			collectSlug( slugs, overrides[ key ] )
		}
	}
}
