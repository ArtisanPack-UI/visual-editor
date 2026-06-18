/**
 * Shared types for the box-shadow feature (#607).
 *
 * Mirrors the PHP-side `BoxShadowResolver` + `BoxShadowEmitter`
 * shapes so the editor's preview canvas stays in sync with the
 * server-rendered front-end. Architecture deliberately parallels
 * `gradient-borders/types.ts` from #490 — see that file's docblock
 * for the cascade rationale.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

/**
 * Raw shadow subtree the inspector writes. Stored on
 * `attributes.style.shadow` for the idle slot and as the value of
 * each entry in `attributes.states['style.shadow']` /
 * `attributes.responsive['style.shadow']` for the per-state /
 * per-breakpoint overrides.
 *
 * A `preset` slug short-circuits the structured fields — when set,
 * the renderer emits `box-shadow: var(--wp--preset--shadow--{slug})`
 * directly and the offset/blur/spread/color/gradient fields are
 * ignored.
 */
export interface ShadowSubtree {
	offsetX?: string | null
	offsetY?: string | null
	blur?: string | null
	spread?: string | null
	color?: string | null
	gradient?: string | null
	inset?: boolean
	preset?: string | null
	[key: string]: unknown
}

/**
 * A single fully-resolved shadow layer. The discriminating field is
 * `gradient` — non-null indicates the gradient ::before/::after
 * emission path, null indicates the stock `box-shadow` declaration
 * path.
 *
 * Preset references collapse into the `preset` field; the emitter
 * uses preset over everything else when set.
 */
export interface ResolvedShadowLayer {
	offsetX: string
	offsetY: string
	blur: string
	spread: string
	color: string | null
	gradient: string | null
	inset: boolean
	preset: string | null
}

export interface ResolvedBoxShadow {
	idle: ResolvedShadowLayer | null
	states: Record<string, ResolvedShadowLayer>
	breakpoints: Record<string, ResolvedShadowLayer>
}

/**
 * Block attribute tree as far as the resolver / warning surface is
 * concerned. The block can carry any other attributes alongside; only
 * the slots we read are typed.
 */
export interface BoxShadowAttributes {
	style?: { shadow?: ShadowSubtree } | null
	states?: Record<string, Record<string, ShadowSubtree | null>> | null
	responsive?: Record<string, Record<string, ShadowSubtree | null>> | null
	[key: string]: unknown
}
