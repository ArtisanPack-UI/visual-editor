<?php

/**
 * CssUrlAbsolutizer tests — relative `url()` rewriting for the theme
 * stylesheet inlined into pattern previews (#832).
 *
 * @since 1.13.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\CssUrlAbsolutizer;

const CSS_URL_BASE = 'https://example.com/themes/acme/';

it( 'absolutizes relative urls', function ( string $css, string $expected ): void {
	expect( CssUrlAbsolutizer::absolutize( $css, CSS_URL_BASE ) )->toBe( $expected );
} )->with( [
	'dot slash'       => [ 'a{background:url(./x.png)}', 'a{background:url(https://example.com/themes/acme/x.png)}' ],
	'bare path'       => [ 'a{background:url(assets/bg.jpg)}', 'a{background:url(https://example.com/themes/acme/assets/bg.jpg)}' ],
	'double quoted'   => [ 'a{background:url("assets/a b.png")}', 'a{background:url("https://example.com/themes/acme/assets/a b.png")}' ],
	'single quoted'   => [ "a{background:url('assets/a.png')}", "a{background:url('https://example.com/themes/acme/assets/a.png')}" ],
	'inner spaces'    => [ 'a{background:url( assets/a.png )}', 'a{background:url(https://example.com/themes/acme/assets/a.png)}' ],
	'parent segment'  => [ 'a{background:url(../shared/a.png)}', 'a{background:url(https://example.com/themes/shared/a.png)}' ],
	'above root'      => [ 'a{background:url(../../../../a.png)}', 'a{background:url(https://example.com/a.png)}' ],
	'query + hash'    => [ '@font-face{src:url(fonts/f.woff2?v=2#iefix)}', '@font-face{src:url(https://example.com/themes/acme/fonts/f.woff2?v=2#iefix)}' ],
	'uppercase URL('  => [ 'a{background:URL(x.png)}', 'a{background:url(https://example.com/themes/acme/x.png)}' ],
] );

it( 'leaves non-relative urls untouched', function ( string $css ): void {
	expect( CssUrlAbsolutizer::absolutize( $css, CSS_URL_BASE ) )->toBe( $css );
} )->with( [
	'https'             => [ 'a{background:url(https://cdn.example.com/a.png)}' ],
	'http quoted'       => [ 'a{background:url("http://cdn.example.com/a.png")}' ],
	'protocol relative' => [ 'a{background:url(//cdn.example.com/a.png)}' ],
	'root relative'     => [ 'a{background:url(/images/a.png)}' ],
	'fragment'          => [ 'a{filter:url(#blur)}' ],
	'data uri'          => [ "a{background:url('data:image/svg+xml;utf8,<svg></svg>')}" ],
	'empty'             => [ 'a{background:url()}' ],
	'no urls'           => [ 'a{color:red}' ],
] );

it( 'keeps the base port and rewrites every occurrence', function (): void {
	$css = 'a{background:url(a.png)}b{background:url("b.png")}';

	expect( CssUrlAbsolutizer::absolutize( $css, 'http://localhost:8000/themes/acme' ) )
		->toBe( 'a{background:url(http://localhost:8000/themes/acme/a.png)}b{background:url("http://localhost:8000/themes/acme/b.png")}' );
} );

it( 'returns the css unchanged without a usable base url', function ( string $base ): void {
	expect( CssUrlAbsolutizer::absolutize( 'a{background:url(a.png)}', $base ) )->toBe( 'a{background:url(a.png)}' );
} )->with( [ '', '/themes/acme/', 'not a url' ] );
