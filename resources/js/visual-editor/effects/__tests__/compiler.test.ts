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
	{ textClip: true },
	{ textClip: true, opacity: 40 },
	{ customCss: '\u00a0' },
	{ zIndex: '\u00a05', opacity: '\u00a050' },
	{ zIndex: ' 5\t', opacity: '\u000b50\f' },
	{ opacity: 50, customCss: 'a:url({);} body{background:red} y{a:url(})' },
	{ opacity: 50, customCss: 'background: url("a.png");' },
]

const ATTRIBUTE_PAYLOADS: Array<Record<string, unknown>> = [
	{ artisanpackEffects: { textClip: true }, gradient: 'cool-to-warm' },
	{ artisanpackEffects: { textClip: true, opacity: 40 }, style: { color: { gradient: 'linear-gradient(red, blue)' } } },
	{ artisanpackEffects: { textClip: true }, style: { background: { backgroundImage: { url: 'a.png' } } } },
	{ artisanpackEffects: { textClip: true }, style: { color: { background: '#000' } } },
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

	it.each( ATTRIBUTE_PAYLOADS )( 'matches the renderer text clip for %j', ( attributes ) => {
		const canvas   = compileEffects( attributes )
		const renderer = rendererCompileEffects( attributes )

		if ( null === renderer ) {
			expect( canvas ).toBeNull()
			return
		}

		expect( canvas?.css.split( canvas.className ).join( 'SCOPE' ) )
			.toBe( renderer.css.split( renderer.className ).join( 'SCOPE' ) )
	} )

	it( 'drops text clip without a gradient or background image', () => {
		expect( compileEffects( { artisanpackEffects: { textClip: true } } ) ).toBeNull()
		expect( compileEffects( { artisanpackEffects: { textClip: true } }, { hasBackgroundFill: true } )?.css )
			.toContain( '@media (forced-colors:active)' )
	} )

	it( 'sanitizes Custom CSS like the renderers', () => {
		for ( const css of [
			'& img { color: red; }',
			'@import url(x.css);',
			'a { b',
			'content: "x  y";',
			'background: url(https://example.com/a.png);',
			'background: url("https://example.com/a.png");',
			"a:url(x'){}'",
			'color: rgb(',
			'a[href{}',
			'a)',
			'a:( { ) }',
			'content:"("',
			'content:"[x"',
			'\u00a0',
		] ) {
			expect( sanitizeCustomCss( css ) ).toBe( rendererSanitizeCustomCss( css ) )
			expect( sanitizeCustomCss( css, false ) ).toBe( rendererSanitizeCustomCss( css, false ) )
		}
	} )

	it( 'rejects unquoted URLs and unbalanced brackets', () => {
		for ( const css of [ 'background: url(a.png);', 'a:url({);} body{background:red} y{a:url(})', 'color: rgb(', 'a[href{}', 'a)' ] ) {
			expect( sanitizeCustomCss( css ) ).toBeNull()
		}

		expect( sanitizeCustomCss( 'background: url("a.png");', false ) ).toBeNull()
		expect( compileEffects(
			{ artisanpackEffects: { opacity: 50, customCss: 'background: image-set("a.png" 1x);' } },
			{ allowCustomCssUrls: false },
		)?.css ).not.toContain( 'image-set' )
	} )
} )
