/**
 * Flex Panel custom values in the editor canvas (#1039).
 *
 * A custom value such as `ap-basis-[14px]` is a class that only works
 * with a matching rule. The front ends print those rules per page;
 * this module does the same for the canvas, publishing each block's
 * rules into the shared `flex-arbitrary` style host in the head of the
 * document the block renders in (the post editor's iframe or the site
 * editor's in-tree canvas).
 *
 * Mirrors `FlexSupport::buildArbitraryStyles()` and
 * `FlexSupport::keywordOverrideRules()` in the Blade renderer, and the
 * React and Vue ports of both.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { FLEX_LAYOUT_BASE_RULES } from '../../../editor/flex-layout-css'
import { BASE_KEY } from '../../../responsive/types'
import type { BreakpointRegistry } from '../../../responsive/registry'
import { useCanvasScopedStyle } from '../../../support/canvas-scoped-styles'
import type { ArbitraryRule, SerializeResult } from './serializer'
import { safeCssValue } from '../../../support/safe-css-value'

/**
 * Shared canvas style host the custom flex rules are published into.
 *
 * @since 1.20.0
 */
export const FLEX_ARBITRARY_STYLE_CHANNEL = 'flex-arbitrary'

const KEYWORD_RULE = /^\.([a-z0-9-]+) \{ ([a-z-]+): ([^;{}]+); \}$/

let keywordDeclarations: Map<string, { property: string; value: string }> | null = null

function escapeSelector( className: string ): string {
	return className.replace( /[^a-zA-Z0-9_-]/g, ( char ) => `\\${ char }` )
}

/**
 * Rules for a block's breakpoint keyword classes that compete with one
 * of its own custom values (#949). The static sheet styling
 * `mobile:ap-basis-auto` loads before the custom rules, which would
 * otherwise win at every width.
 */
function keywordOverrideRules( result: SerializeResult ): ArbitraryRule[] {
	const customProperties = new Set( result.arbitraryRules.map( ( rule ) => rule.property ) )

	if ( 0 === customProperties.size ) {
		return []
	}

	if ( null === keywordDeclarations ) {
		keywordDeclarations = new Map()

		for ( const rule of FLEX_LAYOUT_BASE_RULES ) {
			const match = KEYWORD_RULE.exec( rule )

			if ( match ) {
				keywordDeclarations.set( match[ 1 ], { property: match[ 2 ], value: match[ 3 ] } )
			}
		}
	}

	const rules: ArbitraryRule[] = []

	for ( const className of result.classes ) {
		const separator = className.indexOf( ':' )

		if ( -1 === separator || className.includes( '[' ) ) {
			continue
		}

		const declaration = keywordDeclarations.get( className.slice( separator + 1 ) )

		if ( declaration && customProperties.has( declaration.property ) ) {
			rules.push( {
				className,
				property: declaration.property,
				value: declaration.value,
				breakpoint: className.slice( 0, separator ),
			} )
		}
	}

	return rules
}

/**
 * The CSS a serialized block needs in the canvas, in breakpoint order.
 * Returns `''` when the block has no custom values.
 *
 * @since 1.20.0
 *
 * @param result   The block's {@link SerializeResult}.
 * @param registry The breakpoints its classes were serialized against.
 */
export function buildFlexCanvasCss(
	result: SerializeResult,
	registry: BreakpointRegistry,
): string {
	const rules = [ ...result.arbitraryRules, ...keywordOverrideRules( result ) ]
	let css     = ''

	for ( const breakpoint of registry.keysWithBase() ) {
		const body = rules
			.filter( ( rule ) => rule.breakpoint === breakpoint )
			.flatMap( ( rule ) => {
				const value = safeCssValue( rule.value )

				return null === value
					? []
					: [ `.${ escapeSelector( rule.className ) } { ${ rule.property }: ${ value }; }` ]
			} )
			.join( ' ' )

		if ( '' === body ) {
			continue
		}

		if ( BASE_KEY === breakpoint ) {
			css += `${ body } `
			continue
		}

		const query = registry.mediaQuery( breakpoint, true )

		if ( null !== query ) {
			css += `@media ${ query } { ${ body } } `
		}
	}

	return css.trim()
}

/**
 * Publishes a block's custom flex rules to its canvas document for as
 * long as the block is mounted.
 *
 * @since 1.20.0
 *
 * @param clientId The block's client ID.
 * @param result   The block's {@link SerializeResult}.
 * @param registry The breakpoints its classes were serialized against.
 */
export function useFlexCanvasStyles(
	clientId: string,
	result: SerializeResult,
	registry: BreakpointRegistry,
): void {
	useCanvasScopedStyle(
		FLEX_ARBITRARY_STYLE_CHANNEL,
		clientId,
		buildFlexCanvasCss( result, registry ),
	)
}
