/**
 * Shared types for the gradient border feature (#490).
 *
 * Mirrors the PHP-side `GradientBorderResolver` + `GradientBorderEmitter`
 * shapes so the editor's preview canvas stays in sync with the
 * server-rendered front-end.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

export interface ResolvedGradientBorder {
	idle: string | null
	states: Record<string, string>
	breakpoints: Record<string, string>
	width: string | null
	radius: string | Record<string, unknown> | null
}

/**
 * Sub-shape of `attributes.style.border` the resolver reads. Only the
 * fields the gradient pipeline touches are listed — the rest of the
 * border attribute tree (color, style, per-side) flows through the
 * standard `BlockSupports::applyBorder` path unchanged.
 */
export interface BorderSubtree {
	gradient?: string | null
	width?: string | null
	radius?: string | Record<string, unknown> | null
	[key: string]: unknown
}

/**
 * Block attribute tree as far as the resolver / warning surface is
 * concerned. The block can carry any other attributes alongside; only
 * the slots we read are typed.
 */
export interface GradientBorderAttributes {
	style?: { border?: BorderSubtree } | null
	states?: Record<string, Record<string, string | null>> | null
	responsive?: Record<string, Record<string, string | null>> | null
	[key: string]: unknown
}
