import { describe, expect, it } from 'vitest'

import { sanitizeBoundHtml } from '../sanitize-bound-html'

describe( 'sanitizeBoundHtml', () => {
	it( 'keeps plain text and inline formatting', () => {
		expect( sanitizeBoundHtml( 'Tom &amp; Jerry' ) ).toBe( 'Tom &amp; Jerry' )
		expect( sanitizeBoundHtml( 'A <em>b</em> <strong class="x">c</strong><br>' ) )
			.toBe( 'A <em>b</em> <strong class="x">c</strong><br>' )
	} )

	it.each( [
		[ '<iframe srcdoc="<script>alert(1)</script>"></iframe>x', 'x' ],
		[ '<iframe src="javascript:alert(1)"></iframe>x', 'x' ],
		[ '<script>alert(1)</script>x', 'x' ],
		[ '<style>h2{display:none}</style>x', 'x' ],
		[ '<object data="javascript:alert(1)"></object>x', 'x' ],
		[ '<embed src="javascript:alert(1)">x', 'x' ],
		[ '<svg><script>alert(1)</script></svg>x', 'x' ],
		[ '<svg onload="alert(1)"></svg>x', 'x' ],
		[ '<math><mi href="javascript:alert(1)"></mi></math>x', 'x' ],
		[ '<template><img src=x onerror=alert(1)></template>x', 'x' ],
		[ '<img src="x" onerror="alert(1)">x', 'x' ],
		[ '<!-- <img src=x onerror=alert(1)> -->x', 'x' ],
	] )( 'removes active content from %s', ( input, expected ) => {
		expect( sanitizeBoundHtml( input ) ).toBe( expected )
	} )

	it( 'unwraps disallowed elements but keeps their text', () => {
		expect( sanitizeBoundHtml( '<div><p>Kept <em>text</em></p></div>' ) )
			.toBe( 'Kept <em>text</em>' )
	} )

	it( 'cleans what it unwraps', () => {
		expect( sanitizeBoundHtml( '<div><iframe src="x"></iframe><b onclick="x">y</b></div>' ) )
			.toBe( '<b>y</b>' )
	} )

	it( 'leaves markup inside a raw-text element escaped', () => {
		expect( sanitizeBoundHtml( '<textarea><img src=x onerror=alert(1)></textarea>' ) )
			.toBe( '&lt;img src=x onerror=alert(1)&gt;' )
	} )

	it( 'drops attributes outside the allowlist', () => {
		expect( sanitizeBoundHtml( '<span style="position:fixed" id="a" onmouseover="x" class="b">c</span>' ) )
			.toBe( '<span class="b">c</span>' )
	} )

	it.each( [
		'javascript:alert(1)',
		'JaVaScRiPt:alert(1)',
		' javascript:alert(1)',
		'java\tscript:alert(1)',
		'data:text/html,<script>alert(1)</script>',
		'vbscript:x',
	] )( 'removes the unsafe href %j', ( href ) => {
		const link = document.createElement( 'a' )

		link.setAttribute( 'href', href )
		link.textContent = 'x'

		expect( sanitizeBoundHtml( link.outerHTML ) ).toBe( '<a>x</a>' )
	} )

	it.each( [
		[ 'target="_blank" rel="opener"', 'target="_blank" rel="noopener"' ],
		[ 'target="_blank" rel="OPENER nofollow"', 'target="_blank" rel="nofollow noopener"' ],
		[ 'target="_blank"', 'target="_blank" rel="noopener"' ],
		[ 'target="_BLANK" rel="NoOpener"', 'target="_BLANK" rel="NoOpener"' ],
		[ 'target="_blank" rel="noreferrer"', 'target="_blank" rel="noreferrer noopener"' ],
		[ 'target="named-window"', '' ],
		[ 'target="_top"', '' ],
		[ 'target="_parent" rel="nofollow"', 'rel="nofollow"' ],
		[ 'rel="opener"', '' ],
		[ 'rel="nofollow"', 'rel="nofollow"' ],
	] )( 'keeps a link from reaching the editor window: %s', ( attributes, expected ) => {
		const output = expected ? ` ${ expected }` : ''

		expect( sanitizeBoundHtml( `<a href="/a" ${ attributes }>x</a>` ) )
			.toBe( `<a href="/a"${ output }>x</a>` )
	} )

	it.each( [
		'https://example.com/a',
		'http://example.com',
		'mailto:a@example.com',
		'tel:+15551234',
		'/relative/path',
		'#section',
	] )( 'keeps the safe href %s', ( href ) => {
		expect( sanitizeBoundHtml( `<a href="${ href }">x</a>` ) ).toBe( `<a href="${ href }">x</a>` )
	} )
} )
