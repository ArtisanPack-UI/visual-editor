/**
 * Splits an Image block's state paths for the canvas (#1052).
 *
 * The Image block paints its border and shadow on the `<img>`, so their state
 * overrides target it too, under `.scope img`, and a `transition` is copied so
 * they still animate. A gradient border or shadow stays on the wrapper, which
 * can draw its pseudo-element. Mirrors `BlockSupports::splitBorderAndShadow()`.
 */

import { hasGradientLayer } from '../box-shadows/emitter'
import { resolveBoxShadow } from '../box-shadows/resolver'
import type { BoxShadowAttributes } from '../box-shadows/types'
import { resolveGradientBorder } from '../gradient-borders/resolver'
import type { GradientBorderAttributes } from '../gradient-borders/types'
import type { StatesByPath } from './css-emitter'

const IMAGE_BLOCKS = [ 'core/image', 'artisanpack/image' ]

export function splitImageStatePaths(
	blockName: string,
	attributes: Record<string, unknown>,
	paths: StatesByPath,
): { wrapper: StatesByPath, image: StatesByPath } {
	if ( ! IMAGE_BLOCKS.includes( blockName ) ) {
		return { wrapper: paths, image: {} }
	}

	const moved: string[] = []
	const shadow          = resolveBoxShadow( attributes as BoxShadowAttributes )

	if ( null === resolveGradientBorder( attributes as GradientBorderAttributes ) ) {
		moved.push( 'border' )
	}

	if ( null === shadow || ! hasGradientLayer( shadow ) ) {
		moved.push( 'shadow' )
	}

	const pattern = new RegExp( `^(style\\.)?(${ moved.join( '|' ) })(\\.|$)` )
	const wrapper: StatesByPath = {}
	const image: StatesByPath   = {}

	for ( const [ path, value ] of Object.entries( paths ) ) {
		( moved.length > 0 && pattern.test( path ) ? image : wrapper )[ path ] = value
	}

	if ( Object.keys( image ).length > 0 ) {
		for ( const path of [ 'transition', 'style.transition' ] ) {
			if ( paths[ path ] ) {
				image[ path ] = paths[ path ]
			}
		}
	}

	return { wrapper, image }
}
