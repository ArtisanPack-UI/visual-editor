<?php

/**
 * Tests for {@see ResolvedPattern}.
 *
 * Focus is on the `post_types` scoping surface introduced by #639.
 * General resolution / coercion of the other fields is exercised by
 * the higher-level adapter and feature tests; these unit cases stay
 * narrow.
 *
 * @since 1.4.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;

it( 'defaults post_types to null when the field is omitted', function (): void {
	$pattern = ResolvedPattern::fromArray( [
		'slug'  => 'hero',
		'title' => 'Hero',
	] );

	expect( $pattern->postTypes )->toBeNull();
} );

it( 'normalizes the post_types array to lowercase de-duplicated slugs', function (): void {
	$pattern = ResolvedPattern::fromArray( [
		'slug'       => 'landing-hero',
		'title'      => 'Landing Hero',
		'post_types' => [ 'Page', 'page', ' LANDING ', 42, null, 'landing' ],
	] );

	// Duplicates are removed and non-string entries are filtered out.
	expect( $pattern->postTypes )->toBe( [ 'page', 'landing' ] );
} );

it( 'collapses a non-array post_types field back to null', function (): void {
	$pattern = ResolvedPattern::fromArray( [
		'slug'       => 'hero',
		'title'      => 'Hero',
		'post_types' => 'page',
	] );

	// A misconfigured contributor shouldn't crash; treat malformed
	// scope as "unscoped" so the pattern remains available.
	expect( $pattern->postTypes )->toBeNull();
} );

it( 'matches every post type when postTypes is null (Gutenberg convention)', function (): void {
	$pattern = ResolvedPattern::fromArray( [
		'slug'  => 'hero',
		'title' => 'Hero',
	] );

	expect( $pattern->matchesPostType( 'page' ) )->toBeTrue()
		->and( $pattern->matchesPostType( 'post' ) )->toBeTrue()
		->and( $pattern->matchesPostType( 'anything-goes' ) )->toBeTrue();
} );

it( 'matches only the whitelisted post types when postTypes is set', function (): void {
	$pattern = ResolvedPattern::fromArray( [
		'slug'       => 'landing-hero',
		'title'      => 'Landing Hero',
		'post_types' => [ 'page', 'landing' ],
	] );

	expect( $pattern->matchesPostType( 'page' ) )->toBeTrue()
		->and( $pattern->matchesPostType( 'landing' ) )->toBeTrue()
		->and( $pattern->matchesPostType( 'post' ) )->toBeFalse();
} );

it( 'lowercases and trims the input slug before matching (defensive normalization)', function (): void {
	$pattern = ResolvedPattern::fromArray( [
		'slug'       => 'landing-hero',
		'title'      => 'Landing Hero',
		'post_types' => [ 'page' ],
	] );

	// The controller already lower-cases the query param, but the
	// method has to survive being called directly with a padded /
	// mixed-case slug — otherwise a future consumer silently misses
	// a match on `'Page'` or `' page '`.
	expect( $pattern->matchesPostType( 'Page' ) )->toBeTrue()
		->and( $pattern->matchesPostType( ' page ' ) )->toBeTrue()
		->and( $pattern->matchesPostType( 'PAGE' ) )->toBeTrue();
} );

it( 'matches nothing when postTypes is explicitly the empty list', function (): void {
	// A contributor that hands us `post_types: []` scoped the pattern
	// to zero contexts. Treating this as "match everywhere" would
	// let a misregistered scope leak everywhere — instead, respect
	// the empty scope and match nothing.
	$pattern = ResolvedPattern::fromArray( [
		'slug'       => 'nowhere',
		'title'      => 'Nowhere',
		'post_types' => [],
	] );

	expect( $pattern->postTypes )->toBe( [] )
		->and( $pattern->matchesPostType( 'page' ) )->toBeFalse()
		->and( $pattern->matchesPostType( 'post' ) )->toBeFalse();
} );

describe( 'pattern header fields (cms-framework 2.15 parity)', function (): void {
	it( 'defaults keywords and template types to empty and keeps the pattern in the inserter', function (): void {
		$pattern = ResolvedPattern::fromArray( [ 'slug' => 'hero', 'title' => 'Hero' ] );

		expect( $pattern->keywords )->toBe( [] )
			->and( $pattern->inserter )->toBeTrue()
			->and( $pattern->templateTypes )->toBe( [] )
			->and( $pattern->viewportWidth )->toBeNull();
	} );

	it( 'reads the fields cms-framework\'s toFilterEntry() emits', function (): void {
		$pattern = ResolvedPattern::fromArray( [
			'slug'           => 'twentytwentyfive/hidden-404',
			'title'          => '404',
			'source'         => 'theme',
			'keywords'       => [ 'starter', 'not found' ],
			'inserter'       => false,
			'template_types' => [ '404' ],
			'post_types'     => null,
			'viewport_width' => 1400,
		] );

		expect( $pattern->keywords )->toBe( [ 'starter', 'not found' ] )
			->and( $pattern->inserter )->toBeFalse()
			->and( $pattern->templateTypes )->toBe( [ '404' ] )
			->and( $pattern->postTypes )->toBeNull()
			->and( $pattern->viewportWidth )->toBe( 1400 );
	} );

	it( 'trims, de-duplicates and drops non-string list members', function (): void {
		$pattern = ResolvedPattern::fromArray( [
			'slug'           => 'hero',
			'title'          => 'Hero',
			'keywords'       => [ ' hero ', 'hero', '', 7, [ 'x' ], 'banner' ],
			'template_types' => [ 'home', null, ' home', 'front-page' ],
		] );

		expect( $pattern->keywords )->toBe( [ 'hero', 'banner' ] )
			->and( $pattern->templateTypes )->toBe( [ 'home', 'front-page' ] );
	} );

	it( 'collapses non-list keywords and template types to empty', function ( mixed $raw ): void {
		$pattern = ResolvedPattern::fromArray( [ 'slug' => 'hero', 'title' => 'Hero', 'keywords' => $raw, 'template_types' => $raw ] );

		expect( $pattern->keywords )->toBe( [] )
			->and( $pattern->templateTypes )->toBe( [] );
	} )->with( [
		'string' => [ 'hero, banner' ],
		'int'    => [ 3 ],
		'null'   => [ null ],
	] );

	it( 'reads the inserter flag', function ( mixed $raw, bool $expected ): void {
		expect( ResolvedPattern::fromArray( [ 'slug' => 'hero', 'title' => 'Hero', 'inserter' => $raw ] )->inserter )->toBe( $expected );
	} )->with( [
		'true'          => [ true, true ],
		'false'         => [ false, false ],
		'null'          => [ null, true ],
		'empty string'  => [ '', true ],
		'string no'     => [ 'no', false ],
		'string false'  => [ ' false ', false ],
		'string yes'    => [ 'yes', true ],
		'int zero'      => [ 0, false ],
		'int one'       => [ 1, true ],
		'unrecognized'  => [ 'maybe', true ],
		'array'         => [ [ false ], true ],
	] );
} );
