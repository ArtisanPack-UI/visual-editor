<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\QueryPostTypeRegistry;

/*
 * Tests for {@see QueryPostTypeRegistry} (#847) — the server side of the
 * Query Loop "Post type" picker. Hosts extend the list through the
 * `ap.visualEditor.query.postTypes` filter.
 */

beforeEach( function (): void {
	removeAllFilters( QueryPostTypeRegistry::FILTER );
} );

afterEach( function (): void {
	removeAllFilters( QueryPostTypeRegistry::FILTER );
} );

it( 'defaults to Posts and Pages', function (): void {
	expect( QueryPostTypeRegistry::resolve() )->toBe( [
		[ 'value' => 'post', 'label' => 'Posts' ],
		[ 'value' => 'page', 'label' => 'Pages' ],
	] );
} );

it( 'lets hosts append their own content types', function (): void {
	addFilter( QueryPostTypeRegistry::FILTER, function ( array $options ): array {
		$options[] = [ 'value' => 'case-study', 'label' => 'Case Studies' ];

		return $options;
	} );

	expect( QueryPostTypeRegistry::resolve() )->toBe( [
		[ 'value' => 'post', 'label' => 'Posts' ],
		[ 'value' => 'page', 'label' => 'Pages' ],
		[ 'value' => 'case-study', 'label' => 'Case Studies' ],
	] );
} );

it( 'lets hosts replace the list outright', function (): void {
	addFilter( QueryPostTypeRegistry::FILTER, fn (): array => [
		[ 'value' => 'event', 'label' => 'Events' ],
	] );

	expect( QueryPostTypeRegistry::resolve() )->toBe( [
		[ 'value' => 'event', 'label' => 'Events' ],
	] );
} );

it( 'drops malformed entries, trims values, and keeps the first duplicate', function (): void {
	addFilter( QueryPostTypeRegistry::FILTER, fn (): array => [
		[ 'value' => ' product ', 'label' => ' Products ' ],
		[ 'value' => 'product', 'label' => 'Duplicate' ],
		[ 'value' => 'no-label', 'label' => '' ],
		[ 'value' => '../etc', 'label' => 'Traversal' ],
		[ 'value' => 42, 'label' => 'Numeric' ],
		[ 'label' => 'Missing value' ],
		'not-an-array',
	] );

	expect( QueryPostTypeRegistry::resolve() )->toBe( [
		[ 'value' => 'product', 'label' => 'Products' ],
	] );
} );

it( 'falls back to the defaults when the filter returns a non-array', function (): void {
	addFilter( QueryPostTypeRegistry::FILTER, fn (): string => 'nope' );

	expect( QueryPostTypeRegistry::resolve() )->toHaveCount( 2 )
		->and( QueryPostTypeRegistry::resolve()[0]['value'] )->toBe( 'post' );
} );

it( 'falls back to the defaults when no filtered entry is valid', function (): void {
	addFilter( QueryPostTypeRegistry::FILTER, fn (): array => [] );

	expect( array_column( QueryPostTypeRegistry::resolve(), 'value' ) )->toBe( [ 'post', 'page' ] );
} );

it( 'drops values longer than the 64-character preview limit', function (): void {
	addFilter( QueryPostTypeRegistry::FILTER, fn (): array => [
		[ 'value' => str_repeat( 'a', 65 ), 'label' => 'Too long' ],
		[ 'value' => str_repeat( 'b', 64 ), 'label' => 'Just fits' ],
	] );

	expect( array_column( QueryPostTypeRegistry::resolve(), 'label' ) )->toBe( [ 'Just fits' ] );
} );
