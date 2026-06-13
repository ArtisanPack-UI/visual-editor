/**
 * Client-side CSS emitter for gradient borders (#490).
 *
 * Mirrors the PHP `GradientBorderEmitter` byte-for-byte so the editor
 * canvas preview matches the rendered front-end. See the PHP file's
 * docblock for the strategy rationale (single mask-pseudo emission
 * for all gradient kinds × radius combinations).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import type { BreakpointRegistry } from '../responsive/registry'
import type { StateRegistry } from '../states/registry'
import type { ResolvedGradientBorder } from './types'

export const DEFAULT_TRANSITION =
	'background 200ms ease, opacity 200ms ease'

const CSS_WHITELIST     = /[^a-zA-Z0-9_+\-*/.,()%#\s]/g
const GRADIENT_WHITELIST = /[^a-zA-Z0-9_+\-*/.,()%#:\s]/g

function sanitizeCss( value: string ): string {
	return value.replace( CSS_WHITELIST, '' )
}

function sanitizeGradient( value: string ): string {
	return value.replace( GRADIENT_WHITELIST, '' )
}

function radiusDeclaration( radius: ResolvedGradientBorder[ 'radius' ] ): string {
	if ( 'string' === typeof radius && '' !== radius.trim() ) {
		return `border-radius:${ sanitizeCss( radius ) };`
	}

	if ( radius && 'object' === typeof radius ) {
		const pieces: string[] = []

		const corners: Array<[string, string]> = [
			[ 'topLeft', 'top-left' ],
			[ 'topRight', 'top-right' ],
			[ 'bottomLeft', 'bottom-left' ],
			[ 'bottomRight', 'bottom-right' ],
		]

		for ( const [ key, cssKey ] of corners ) {
			const value = ( radius as Record<string, unknown> )[ key ]

			if ( 'string' !== typeof value || '' === value.trim() ) {
				continue
			}

			pieces.push( `border-${ cssKey }-radius:${ sanitizeCss( value ) }` )
		}

		if ( 0 < pieces.length ) {
			return pieces.join( ';' ) + ';'
		}
	}

	return 'border-radius:inherit;'
}

function baseBeforeDeclarations(
	gradient: string,
	width: string,
	radius: ResolvedGradientBorder[ 'radius' ],
): string {
	const radiusDecl = radiusDeclaration( radius )
	const safeWidth  = sanitizeCss( width )

	// `inset: calc(-1 * <width>)` extends the pseudo OUTWARD by the
	// border-width so its outer edge aligns with the wrapper's
	// border-box. Combined with `padding: <width>`, the mask cut-out
	// leaves a `<width>`-wide ring sitting exactly where the wrapper's
	// native border would render. Mirrors the PHP emitter — see the
	// docblock there for the visual rationale.
	return (
		`content:"";position:absolute;inset:calc(-1 * ${ safeWidth });padding:${ safeWidth };`
		+ radiusDecl
		+ `background:${ sanitizeGradient( gradient ) };`
		+ '-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
		+ '-webkit-mask-composite:xor;'
		+ 'mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
		+ 'mask-composite:exclude;pointer-events:none'
	)
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
 * comma-separated list. Mirrors the PHP-side `appendPseudoToList`
 * — see that docblock for the failure mode this prevents (a naive
 * `${selector}::before` only suffixes the last selector).
 */
function appendPseudoToList( selector: string, pseudo: string ): string {
	return selector
		.split( ',' )
		.map( ( s ) => s.trim() )
		.filter( ( s ) => '' !== s )
		.map( ( s ) => s + pseudo )
		.join( ', ' )
}

/**
 * Emit the scoped CSS for a single block scope. Empty string when
 * the payload contains no actionable values.
 */
export function emitGradientBorderCss(
	scope: string,
	payload: ResolvedGradientBorder | null | undefined,
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
	const width    = payload.width ?? '1px'

	const hasNonIdle =
		0 < Object.keys( stateMap ).length || 0 < Object.keys( bpMap ).length

	if ( null === idle && ! hasNonIdle ) {
		return ''
	}

	const rules: string[] = []

	// `border-color: transparent !important` suppresses any stale
	// `style.border.color` value left over from before the block was
	// switched to a gradient border (or written by a host that still
	// has the native color picker enabled). Without it the native
	// `applyBorder` would paint a solid edge underneath our gradient
	// `::before` and the user would see two stacked borders.
	rules.push(
		`${ trimmedScope }{position:relative;border-color:transparent !important}`,
	)

	const idleGradient = idle ?? 'transparent'
	rules.push(
		`${ trimmedScope }::before{${ baseBeforeDeclarations( idleGradient, width, payload.radius ) }}`,
	)

	if ( hasNonIdle ) {
		rules.push(
			`${ trimmedScope }::before{transition:${ DEFAULT_TRANSITION }}`,
		)
	}

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

		const rule = `${ appendPseudoToList( selector, '::before' ) }{background:${ sanitizeGradient( stateMap[ stateKey ] ) }}`

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

	for ( const breakpointKey of Object.keys( bpMap ) ) {
		const minWidth = breakpoints.get( breakpointKey )

		if ( null === minWidth ) {
			continue
		}

		rules.push(
			`@media (min-width:${ minWidth }px){${ trimmedScope }::before{background:${ sanitizeGradient( bpMap[ breakpointKey ] ) }}}`,
		)
	}

	return rules.join( '' )
}
