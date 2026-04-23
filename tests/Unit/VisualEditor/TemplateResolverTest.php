<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Resources\TemplateResolver;

function makeTemplate( string $slug, string $theme = 'artisanpack-base' ): VisualEditorTemplate
{
	return VisualEditorTemplate::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => [] ],
		'theme'   => $theme,
		'source'  => 'theme',
		'origin'  => 'theme',
	] );
}

it( 'returns null when the table is empty', function () {
	$resolver = new TemplateResolver();

	expect( $resolver->forSlug( 'single-post' ) )->toBeNull();
} );

it( 'returns the exact slug match when present', function () {
	$resolver = new TemplateResolver();

	$target = makeTemplate( 'single-post' );
	makeTemplate( 'single' );
	makeTemplate( 'index' );

	expect( $resolver->forSlug( 'single-post' )->id )->toBe( $target->id );
} );

it( 'falls back to single when single-{slug} is missing', function () {
	$resolver = new TemplateResolver();

	$single = makeTemplate( 'single' );
	makeTemplate( 'index' );

	expect( $resolver->forSlug( 'single-post' )->id )->toBe( $single->id );
} );

it( 'falls back to page when page-{slug} is missing', function () {
	$resolver = new TemplateResolver();

	$page = makeTemplate( 'page' );
	makeTemplate( 'index' );

	expect( $resolver->forSlug( 'page-about' )->id )->toBe( $page->id );
} );

it( 'falls back to index when single and page are missing', function () {
	$resolver = new TemplateResolver();

	$index = makeTemplate( 'index' );

	expect( $resolver->forSlug( 'single-post' )->id )->toBe( $index->id );
	expect( $resolver->forSlug( 'page-about' )->id )->toBe( $index->id );
	expect( $resolver->forSlug( 'archive' )->id )->toBe( $index->id );
} );

it( 'returns null when even index is missing', function () {
	$resolver = new TemplateResolver();

	makeTemplate( 'single' );

	// No page, no index. single-post → single exists → OK.
	expect( $resolver->forSlug( 'single-post' )->slug )->toBe( 'single' );
	// But page-about has no fallback candidates in the DB.
	expect( $resolver->forSlug( 'page-about' ) )->toBeNull();
	// An unrelated slug also misses.
	expect( $resolver->forSlug( 'archive' ) )->toBeNull();
} );

it( 'scopes lookups to a theme when provided', function () {
	$resolver = new TemplateResolver();

	makeTemplate( 'index', 'theme-a' );
	$themeBIndex = makeTemplate( 'index', 'theme-b' );

	expect( $resolver->forSlug( 'single-post', 'theme-b' )->id )
		->toBe( $themeBIndex->id );

	// theme-c has nothing — cascade terminates.
	expect( $resolver->forSlug( 'single-post', 'theme-c' ) )->toBeNull();
} );

it( 'exposes the fallback chain for introspection', function () {
	$resolver = new TemplateResolver();

	expect( $resolver->fallbackChain( 'single-post' ) )->toBe( [ 'single-post', 'single', 'index' ] );
	expect( $resolver->fallbackChain( 'page-about' ) )->toBe( [ 'page-about', 'page', 'index' ] );
	expect( $resolver->fallbackChain( 'single' ) )->toBe( [ 'single', 'index' ] );
	expect( $resolver->fallbackChain( 'page' ) )->toBe( [ 'page', 'index' ] );
	expect( $resolver->fallbackChain( 'archive' ) )->toBe( [ 'archive', 'index' ] );
	expect( $resolver->fallbackChain( 'index' ) )->toBe( [ 'index' ] );
} );
