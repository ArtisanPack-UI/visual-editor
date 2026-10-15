/**
 * The canvas effects compiler (#881) must print the CSS the renderers
 * print — only the scope hash may differ.
 */

import { describe, expect, it } from 'vitest'

import { compileEffects, sanitizeCustomCss } from '../compiler'
import {
	compileEffects as rendererCompileEffects,
	sanitizeCustomCss as rendererSanitizeCustomCss,
} from '../../../../../packages/visual-editor-renderer-react/src/support/effects'

const PAYLOADS: Array<Record<string, unknown>> = [
	{ opacity: 80, overflowX: 'hidden', backdropBlur: 12, backdropSaturate: 180 },
	{ mixBlendMode: 'screen', isolation: true, zIndex: -2.5, clipShape: 'hexagon', textClip: true },
	{ opacity: 50, customCss: 'border: 1px solid red;\n& > img { border-radius: 8px; }' },
	{ opacity: 50, customCss: 'color: red; } body { display: none' },
	{ opacity: 'nope', overflowY: 'sideways' },
]

describe( 'canvas effects compiler', () => {
	it.each( PAYLOADS )( 'matches the renderer CSS for %j', ( effects ) => {
		const canvas   = compileEffects( { artisanpackEffects: effects } )
		const renderer = rendererCompileEffects( { artisanpackEffects: effects } )

		if ( null === renderer ) {
			expect( canvas ).toBeNull()
			return
		}

		expect( canvas?.className ).toMatch( /^ve-fx-[0-9a-f]{8}$/ )
		expect( canvas?.css.split( canvas.className ).join( 'SCOPE' ) )
			.toBe( renderer.css.split( renderer.className ).join( 'SCOPE' ) )
	} )

	it( 'sanitizes Custom CSS like the renderers', () => {
		for ( const css of [ '& img { color: red; }', '@import url(x.css);', 'a { b', 'content: "x  y";' ] ) {
			expect( sanitizeCustomCss( css ) ).toBe( rendererSanitizeCustomCss( css ) )
		}
	} )
} )
