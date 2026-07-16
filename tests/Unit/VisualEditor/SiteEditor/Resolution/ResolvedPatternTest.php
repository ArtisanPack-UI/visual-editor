<?php

/**
 * Tests for {@see \ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern}.
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
