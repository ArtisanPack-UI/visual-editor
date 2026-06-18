/**
 * Client-side CSS emitter for box shadows (#607).
 *
 * Mirrors the PHP `BoxShadowEmitter` byte-for-byte so the editor
 * canvas preview matches the rendered front-end. See the PHP file's
 * docblock for the per-mode CSS strategy rationale.
 *
 * Three emission modes, dispatched per resolved layer:
 *
 *   1. **Preset** (`layer.preset` set) — `box-shadow: var(--wp--preset--shadow--{slug})`.
 *      Inset is honored via `var(...) inset` (concatenated by the
 *      shadow var convention — themes ship the value WITHOUT the
 *      `inset` keyword).
 *
 *   2. **Solid** (`layer.gradient` null) — stock `box-shadow:
 *      [inset] X Y blur spread color`.
 *
 *   3. **Gradient** (`layer.gradient` set) — `::before` (outer) or
 *      `::after` (inset) pseudo with `background: <gradient>`,
 *      `filter: blur(<blur>)`, `transform: translate(<X>, <Y>)`, and
 *      for inset, a `mask-composite: exclude` ring mask to clip the
 *      fill to the inside of the wrapper.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import type { BreakpointRegistry } from '../responsive/registry'
import type { StateRegistry } from '../states/registry'
import type { ResolvedBoxShadow, ResolvedShadowLayer } from './types'

export const DEFAULT_TRANSITION =
	'box-shadow 200ms ease, background 200ms ease, opacity 200ms ease, transform 200ms ease'

const CSS_WHITELIST      = /[^a-zA-Z0-9_+\-*/.,()%#\s]/g
const GRADIENT_WHITELIST = /[^a-zA-Z0-9_+\-*/.,()%#:\s]/g
const SLUG_WHITELIST     = /[^a-z0-9_-]/gi

function sanitizeCss( value: string ): string {
	return value.replace( CSS_WHITELIST, '' )
}

function sanitizeGradient( value: string ): string {
	return value.replace( GRADIENT_WHITELIST, '' )
}

function sanitizeSlug( value: string ): string {
	return value.replace( SLUG_WHITELIST, '' )
}

/**
 * Compose the `box-shadow` declaration for a solid (non-gradient,
 * non-preset) layer. Color falls back to `currentColor` if missing
 * — matches the WP core shadow panel default.
 */
function solidBoxShadow( layer: ResolvedShadowLayer ): string {
	const parts = [
		sanitizeCss( layer.offsetX ),
		sanitizeCss( layer.offsetY ),
		sanitizeCss( layer.blur ),
		sanitizeCss( layer.spread ),
		sanitizeCss( layer.color ?? 'currentColor' ),
	].join( ' ' )

	return layer.inset ? `inset ${ parts }` : parts
}

function presetBoxShadow( layer: ResolvedShadowLayer ): string {
	const slug = sanitizeSlug( layer.preset ?? '' )

	if ( '' === slug ) {
		return ''
	}

	const value = `var(--wp--preset--shadow--${ slug })`

	return layer.inset ? `${ value } inset` : value
}

/**
 * Build the gradient ::before (outer) or ::after (inset) declaration
 * body. The wrapper itself gets `position: relative` + no native
 * box-shadow — the pseudo paints the gradient + filter-blur.
 */
function gradientPseudoDeclarations( layer: ResolvedShadowLayer ): string {
	const offsetX = sanitizeCss( layer.offsetX )
	const offsetY = sanitizeCss( layer.offsetY )
	const blur    = sanitizeCss( layer.blur )
	const spread  = sanitizeCss( layer.spread )
	const fill    = sanitizeGradient( layer.gradient ?? 'transparent' )

	if ( layer.inset ) {
		// Inset gradient: ::after sits INSIDE the wrapper, padded by
		// `spread` so the gradient fill is clipped to a ring near
		// the inside edge via `mask-composite: exclude`. The
		// translate moves the inner fill OPPOSITE the offset so the
		// "light source" reads as outside the box. `filter: blur()`
		// softens the ring. `border-radius: inherit` keeps the mask
		// curve matched to the wrapper's own corners. Explicit
		// top/left/width/height (rather than `inset: 0`) for the same
		// reason the outer path uses them — unambiguous sizing across
		// containing-block edge cases.
		return (
			'content:"";position:absolute;top:0;left:0;width:100%;height:100%;'
			+ 'border-radius:inherit;'
			+ `padding:${ spread };`
			+ `background:${ fill };`
			+ `filter:blur(${ blur });`
			+ `transform:translate(calc(-1 * ${ offsetX }),calc(-1 * ${ offsetY }));`
			+ '-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
			+ '-webkit-mask-composite:xor;'
			+ 'mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
			+ 'mask-composite:exclude;'
			+ 'pointer-events:none'
		)
	}

	// Outer gradient: ::before extends OUTWARD by `spread` via explicit
	// top/left + width/height so the fill bleeds past the wrapper edge
	// before `filter: blur()` softens it into a shadow. The original
	// shorthand `inset: calc(-1 * spread)` is spec-equivalent but some
	// browsers / containing-block setups don't resolve a 0-sized
	// absolutely-positioned element correctly from inset alone — the
	// pseudo collapses to its (empty) content. Explicit dimensions
	// make the sizing unambiguous. `z-index: -1` keeps the gradient
	// behind the wrapper content.
	return (
		'content:"";position:absolute;'
		+ `top:calc(-1 * ${ spread });left:calc(-1 * ${ spread });`
		+ `width:calc(100% + 2 * ${ spread });height:calc(100% + 2 * ${ spread });`
		+ 'border-radius:inherit;'
		+ `background:${ fill };`
		+ `filter:blur(${ blur });`
		+ `transform:translate(${ offsetX },${ offsetY });`
		+ 'z-index:-1;'
		+ 'pointer-events:none'
	)
}

function isGradient( layer: ResolvedShadowLayer ): boolean {
	return null === layer.preset && null !== layer.gradient
}

function isPreset( layer: ResolvedShadowLayer ): boolean {
	return null !== layer.preset
}

function pseudoForLayer( layer: ResolvedShadowLayer ): '::before' | '::after' {
	return layer.inset ? '::after' : '::before'
}

function selectorFor( scope: string, selector: string ): string {
	if ( '' === selector ) {
		return ''
	}

	return selector
		.split( ',' )
		.map( ( s ) => s.trim() )
		.filter( ( s ) => '' !== s )
		.map( ( piece ) =>
			piece.includes( '&' )
				? piece.replaceAll( '&', scope )
				: `${ scope }${ piece }`,
		)
		.join( ', ' )
}

/**
 * Append a pseudo-element suffix to every selector in a
 * comma-separated list. Mirrors gradient-borders' helper of the same
 * name — see that docblock for the failure mode this prevents.
 */
function appendPseudoToList( selector: string, pseudo: string ): string {
	return selector
		.split( ',' )
		.map( ( s ) => s.trim() )
		.filter( ( s ) => '' !== s )
		.map( ( s ) => s + pseudo )
		.join( ', ' )
}

function layerBoxShadowValue( layer: ResolvedShadowLayer ): string {
	if ( isPreset( layer ) ) {
		return presetBoxShadow( layer )
	}

	return solidBoxShadow( layer )
}

/**
 * Emit the scoped CSS for a single block scope. Empty string when
 * the payload contains no actionable values.
 */
export function emitBoxShadowCss(
	scope: string,
	payload: ResolvedBoxShadow | null | undefined,
	states: StateRegistry,
	breakpoints: BreakpointRegistry,
): string {
	const trimmedScope = scope.trim()

	if ( '' === trimmedScope || ! payload ) {
		return ''
	}

	const idle     = payload.idle
	const stateMap = payload.states
	const bpMap    = payload.breakpoints

	const hasNonIdle =
		0 < Object.keys( stateMap ).length || 0 < Object.keys( bpMap ).length

	if ( null === idle && ! hasNonIdle ) {
		return ''
	}

	const rules: string[] = []

	// Whether any layer at any cascade level uses the gradient
	// pseudo-element path. If so, the wrapper needs `position:
	// relative` so the pseudo aligns with the wrapper box.
	const usesGradient =
		( null !== idle && isGradient( idle ) )
		|| Object.values( stateMap ).some( isGradient )
		|| Object.values( bpMap ).some( isGradient )

	if ( usesGradient ) {
		// `isolation: isolate` creates a new stacking context on the
		// wrapper. Without it, the gradient `::before` (which uses
		// `z-index: -1` to slip BEHIND the wrapper's own children) can
		// get hidden behind a parent's background, because z-index: -1
		// in the parent's stacking context puts the pseudo behind that
		// parent too. With `isolation: isolate` the pseudo's `z-index:
		// -1` is constrained to THIS wrapper's stacking context — it
		// goes behind the wrapper's children but stays in front of the
		// editor canvas / page background. Standard CSS-tricks technique
		// for gradient drop shadows.
		rules.push( `${ trimmedScope }{position:relative;isolation:isolate}` )
	}

	// --- Idle layer ---
	if ( null !== idle ) {
		if ( isGradient( idle ) ) {
			rules.push(
				`${ trimmedScope }${ pseudoForLayer( idle ) }{${ gradientPseudoDeclarations( idle ) }}`,
			)
		} else {
			const value = layerBoxShadowValue( idle )
			if ( '' !== value ) {
				rules.push( `${ trimmedScope }{box-shadow:${ value }}` )
			}
		}
	}

	if ( hasNonIdle ) {
		rules.push( `${ trimmedScope }{transition:${ DEFAULT_TRANSITION }}` )
	}

	// --- Per-state layers ---
	const hoverParts:    string[] = []
	const nonHoverParts: string[] = []

	for ( const stateKey of Object.keys( stateMap ) ) {
		const definition = states.get( stateKey )

		if ( ! definition ) {
			continue
		}

		const selector = selectorFor( trimmedScope, definition.selector )

		if ( '' === selector ) {
			continue
		}

		const layer = stateMap[ stateKey ]
		const rule  = stateRuleFor( selector, layer )

		if ( '' === rule ) {
			continue
		}

		if ( definition.hoverMediaWrap ) {
			hoverParts.push( rule )
			continue
		}

		nonHoverParts.push( rule )
	}

	if ( 0 < hoverParts.length ) {
		rules.push( `@media (hover: hover){${ hoverParts.join( '' ) }}` )
	}

	for ( const rule of nonHoverParts ) {
		rules.push( rule )
	}

	// --- Per-breakpoint layers ---
	for ( const breakpointKey of Object.keys( bpMap ) ) {
		const minWidth = breakpoints.get( breakpointKey )

		if ( null === minWidth ) {
			continue
		}

		const layer = bpMap[ breakpointKey ]
		const rule  = breakpointRuleFor( trimmedScope, layer )

		if ( '' === rule ) {
			continue
		}

		rules.push( `@media (min-width:${ minWidth }px){${ rule }}` )
	}

	return rules.join( '' )
}

function stateRuleFor( selector: string, layer: ResolvedShadowLayer ): string {
	if ( isGradient( layer ) ) {
		return `${ appendPseudoToList( selector, pseudoForLayer( layer ) ) }{${ gradientPseudoDeclarations( layer ) }}`
	}

	const value = layerBoxShadowValue( layer )
	if ( '' === value ) {
		return ''
	}

	return `${ selector }{box-shadow:${ value }}`
}

function breakpointRuleFor( scope: string, layer: ResolvedShadowLayer ): string {
	if ( isGradient( layer ) ) {
		return `${ scope }${ pseudoForLayer( layer ) }{${ gradientPseudoDeclarations( layer ) }}`
	}

	const value = layerBoxShadowValue( layer )
	if ( '' === value ) {
		return ''
	}

	return `${ scope }{box-shadow:${ value }}`
}
