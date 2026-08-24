<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\InlineIconContentHydrator;

const INLINE_ICON_SVG_STYLE = 'display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em';

beforeEach( function (): void {
	test()->base = sys_get_temp_dir() . '/inline-icon-hydrator-' . bin2hex( random_bytes( 4 ) );
	mkdir( test()->base . '/fab', 0o755, true );
	file_put_contents( test()->base . '/fab/github.svg', '<svg id="github"><path d="M0 0"/></svg>' );

	test()->hydrator = new InlineIconContentHydrator(
		new IconSvgResolver( [ 'fab' => test()->base . '/fab' ] )
	);
} );

afterEach( function (): void {
	$base = test()->base ?? null;
	if ( is_string( $base ) && is_dir( $base ) ) {
		foreach ( new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST,
		) as $path ) {
			$path->isDir() ? rmdir( $path->getRealPath() ) : unlink( $path->getRealPath() );
		}
		rmdir( $base );
	}
} );

it( 'hydrates a registered-set reference span with the resolved, sized svg', function () {
	$html = '<p>Read more <span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github" aria-hidden="true"></span></p>';

	$result = test()->hydrator->hydrate( $html );

	expect( $result )
		->toContain( 'data-icon-set="fab"' )
		->toContain( 'data-icon-name="github"' )
		->toContain( '<path d="M0 0"/>' )
		->toContain( 'style="' . INLINE_ICON_SVG_STYLE . '"' );
} );

it( 'replaces a stale preview body with the freshly resolved svg', function () {
	// The editor embeds a preview SVG so the icon is visible while
	// authoring; the render pass re-resolves from the data-* reference so
	// the icon auto-updates if the underlying set changes.
	$html = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github"><svg id="stale"/></span>';

	$result = test()->hydrator->hydrate( $html );

	expect( $result )
		->toContain( 'id="github"' )
		->not->toContain( 'id="stale"' );
} );

it( 'empties the span when the set is unknown or removed', function () {
	$html = '<span class="ap-inline-icon" data-icon-set="gone" data-icon-name="github"><svg id="stale"/></span>';

	expect( test()->hydrator->hydrate( $html ) )->toBe(
		'<span class="ap-inline-icon" data-icon-set="gone" data-icon-name="github"></span>'
	);
} );

it( 'empties the span when the icon file is missing', function () {
	$html = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="does-not-exist"></span>';

	expect( test()->hydrator->hydrate( $html ) )->toBe(
		'<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="does-not-exist"></span>'
	);
} );

it( 'leaves a custom-svg inline icon untouched', function () {
	// Custom SVG carries no data-icon-set / data-icon-name — its sanitized
	// markup is embedded directly and must pass through verbatim.
	$html = '<span class="ap-inline-icon" aria-hidden="true"><svg viewBox="0 0 1 1"><path d="M0 0"/></svg></span>';

	expect( test()->hydrator->hydrate( $html ) )->toBe( $html );
} );

it( 'hydrates multiple reference spans in one pass', function () {
	file_put_contents( test()->base . '/fab/gitlab.svg', '<svg id="gitlab"><path d="M1 1"/></svg>' );

	$html = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github"></span>'
		. ' and '
		. '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="gitlab"></span>';

	$result = test()->hydrator->hydrate( $html );

	expect( $result )
		->toContain( 'id="github"' )
		->toContain( 'id="gitlab"' )
		->and( substr_count( $result, 'style="' . INLINE_ICON_SVG_STYLE . '"' ) )->toBe( 2 );
} );

it( 'merges the inline style into a resolved svg that already has a style attribute', function () {
	file_put_contents( test()->base . '/fab/styled.svg', '<svg id="styled" style="color:red"><path d="M0 0"/></svg>' );

	$html = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="styled"></span>';

	expect( test()->hydrator->hydrate( $html ) )
		->toContain( 'style="' . INLINE_ICON_SVG_STYLE . ';color:red"' );
} );

it( 'normalizes a self-closing resolved svg without corrupting the tag', function () {
	file_put_contents( test()->base . '/fab/selfclose.svg', '<svg id="sc" viewBox="0 0 1 1"/>' );

	$html = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="selfclose"></span>';

	expect( test()->hydrator->hydrate( $html ) )->toBe(
		'<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="selfclose"><svg id="sc" viewBox="0 0 1 1" style="' . INLINE_ICON_SVG_STYLE . '"/></span>'
	);
} );

it( 'does not match data-icon-set inside a longer prefixed attribute name', function () {
	// A custom-svg span whose only "data-icon-set"-like token is part of a
	// longer attribute must not be treated as a registered-set reference.
	$html = '<span class="ap-inline-icon" x-data-icon-set="fab"><svg viewBox="0 0 1 1"><path d="M0 0"/></svg></span>';

	expect( test()->hydrator->hydrate( $html ) )->toBe( $html );
} );

it( 'is idempotent — re-hydrating already-resolved content is a no-op', function () {
	$html  = '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github"></span>';
	$once  = test()->hydrator->hydrate( $html );
	$twice = test()->hydrator->hydrate( $once );

	expect( $twice )->toBe( $once );
} );

it( 'resolves the data-* reference regardless of attribute order', function () {
	$html = '<span data-icon-name="github" class="ap-inline-icon" data-icon-set="fab"></span>';

	$result = test()->hydrator->hydrate( $html );

	expect( $result )
		->toContain( 'id="github"' )
		->toContain( 'style="' . INLINE_ICON_SVG_STYLE . '"' );
} );

it( 'leaves content with no inline icons untouched', function () {
	$html = '<p>Just a paragraph <span class="badge">no icon</span> here.</p>';

	expect( test()->hydrator->hydrate( $html ) )->toBe( $html );
} );
