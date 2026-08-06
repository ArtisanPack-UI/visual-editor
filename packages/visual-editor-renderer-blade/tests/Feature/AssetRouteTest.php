<?php

/**
 * Covers the package asset route added for #699 — the bundled block-library
 * and `frontend/*` files must resolve with no `vendor:publish` step.
 *
 * Content types are asserted with `toStartWith()` rather than
 * `assertHeader()` because Laravel appends a charset to text responses.
 */

declare( strict_types=1 );

it( 'serves the bundled block-library stylesheet', function () {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/style.css' );

	$response->assertOk();

	expect( (string) $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/css' );
	expect( $response->streamedContent() )->toContain( '.wp-block-columns' );
} );

it( 'serves the bundled theme stylesheet', function () {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/theme.css' );

	$response->assertOk();

	expect( (string) $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/css' );
} );

it( 'serves the frontend stylesheets linked by the styles component', function ( string $file ) {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/frontend/' . $file );

	$response->assertOk();

	expect( (string) $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/css' );
} )->with( [
	'accordion.css',
	'tabs.css',
	'grid.css',
	'marquee.css',
	'breadcrumbs.css',
	'query-pagination.css',
	'flex-layout.css',
	'photo-grid.css',
	'post-template.css',
	'post-variant.css',
	'masonry.css',
	'social-icons.css',
] );

it( 'serves the frontend scripts with a javascript content type', function ( string $file ) {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/frontend/' . $file );

	$response->assertOk();

	expect( (string) $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/javascript' );
} )->with( [
	'interactivity.js',
	'masonry-fallback.js',
] );

it( 'marks served assets nosniff so a mistyped response cannot be re-interpreted', function () {
	$this->get( '/vendor/visual-editor-renderer-blade/style.css' )
		->assertOk()
		->assertHeader( 'X-Content-Type-Options', 'nosniff' );
} );

it( 'revalidates rather than pinning browsers to pre-upgrade CSS', function () {
	$response = $this->get( '/vendor/visual-editor-renderer-blade/style.css' );

	$response->assertOk();

	expect( (string) $response->headers->get( 'Cache-Control' ) )
		->toContain( 'must-revalidate' )
		->not->toContain( 'immutable' );

	expect( $response->headers->get( 'ETag' ) )->not->toBeNull();
} );

it( 'answers a matching If-None-Match with a 304', function () {
	$etag = $this->get( '/vendor/visual-editor-renderer-blade/style.css' )
		->headers->get( 'ETag' );

	expect( $etag )->not->toBeNull();

	$this->withHeaders( [ 'If-None-Match' => $etag ] )
		->get( '/vendor/visual-editor-renderer-blade/style.css' )
		->assertStatus( 304 );
} );

it( '404s for a missing asset', function () {
	$this->get( '/vendor/visual-editor-renderer-blade/does-not-exist.css' )
		->assertNotFound();
} );

it( '404s for a non-allow-listed extension inside the assets directory', function ( string $path ) {
	$this->get( '/vendor/visual-editor-renderer-blade/' . $path )
		->assertNotFound();
} )->with( [
	'README.md',
	'style',
	'style.css.php',
] );

it( 'refuses to traverse outside the bundled assets directory', function ( string $path ) {
	$this->get( '/vendor/visual-editor-renderer-blade/' . $path )
		->assertNotFound();
} )->with( [
	'../composer.json',
	'../../composer.json',
	'../../src/BlockRenderer.php',
	'frontend/../../../composer.json',
	'block-library/../../../composer.json',
] );

/**
 * A null byte reaching `realpath()` raises a ValueError — a 500 rather than
 * a 404. The route's `where()` pattern rejects the request before then.
 * CR / LF / TAB never get this far: Symfony's `Request` rejects those URIs
 * outright with a 400.
 */
it( '404s on a null byte instead of surfacing a realpath ValueError', function ( string $path ) {
	$this->get( '/vendor/visual-editor-renderer-blade/' . $path )
		->assertNotFound();
} )->with( [
	'null byte mid-path'   => "frontend/x\0/style.css",
	'null byte before ext' => "style\0.css",
] );
