<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\PatternAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;

function makeResolvedPattern( array $overrides = [] ): ResolvedPattern
{
	$defaults = [
		'slug'       => 'hero-banner',
		'title'      => 'Hero Banner',
		'rawContent' => '<!-- wp:cover /-->',
		'blocks'     => [],
		'source'     => 'theme',
		'synced'     => false,
		'categories' => [ 'hero' ],
		'blockTypes' => [ 'core/cover' ],
		'wpId'       => null,
		'postTypes'  => null,
	];

	$args = array_merge( $defaults, $overrides );

	return new ResolvedPattern(
		slug       : $args['slug'],
		title      : $args['title'],
		rawContent : $args['rawContent'],
		blocks     : $args['blocks'],
		source     : $args['source'],
		synced     : $args['synced'],
		categories : $args['categories'],
		blockTypes : $args['blockTypes'],
		wpId       : $args['wpId'],
		postTypes  : $args['postTypes'],
	);
}

describe( 'single-record envelope', function (): void {
	it( 'shapes a theme pattern with slug as id and synced false', function (): void {
		$out = ( new PatternAdapter() )->toArray( makeResolvedPattern() );

		expect( $out )->toMatchArray( [
			'id'          => 'hero-banner',
			'slug'        => 'hero-banner',
			'type'        => 'wp_block',
			'status'      => 'publish',
			'source'      => 'theme',
			'synced'      => false,
			'categories'  => [ 'hero' ],
			'block_types' => [ 'core/cover' ],
		] )
			->and( $out['title'] )->toBe( [ 'rendered' => 'Hero Banner', 'raw' => 'Hero Banner' ] )
			->and( $out['content']['raw'] )->toBe( '<!-- wp:cover /-->' );
	} );

	it( 'surfaces wp_id for user-source patterns and flips source/synced', function (): void {
		$pattern = makeResolvedPattern( [
			'slug'   => 'user/cta',
			'source' => 'user',
			'synced' => true,
			'wpId'   => 88,
		] );

		$out = ( new PatternAdapter() )->toArray( $pattern );

		expect( $out['id'] )->toBe( 88 )
			->and( $out['slug'] )->toBe( 'user/cta' )
			->and( $out['source'] )->toBe( 'user' )
			->and( $out['synced'] )->toBeTrue();
	} );

	it( 'falls back from `wpId = 0` (theme-source sentinel) to slug for `id` (#438)', function (): void {
		$pattern = makeResolvedPattern( [
			'wpId' => 0,
			'slug' => 'hero-banner',
		] );

		$out = ( new PatternAdapter() )->toArray( $pattern );

		expect( $out['id'] )->toBe( 'hero-banner' );
	} );

	it( 'preserves empty category and block_type lists', function (): void {
		$pattern = makeResolvedPattern( [ 'categories' => [], 'blockTypes' => [] ] );

		$out = ( new PatternAdapter() )->toArray( $pattern );

		expect( $out['categories'] )->toBe( [] )
			->and( $out['block_types'] )->toBe( [] );
	} );

	// #639 — the `post_types` field on the adapter output lets the editor
	// filter the pattern grid to entries applicable to the current post
	// type. `null` means "available everywhere" (Gutenberg convention).
	it( 'emits post_types as null for an unscoped pattern (#639)', function (): void {
		$out = ( new PatternAdapter() )->toArray( makeResolvedPattern() );

		expect( $out )->toHaveKey( 'post_types' )
			->and( $out['post_types'] )->toBeNull();
	} );

	it( 'passes through the post_types whitelist verbatim (#639)', function (): void {
		$pattern = makeResolvedPattern( [ 'postTypes' => [ 'page', 'landing' ] ] );

		$out = ( new PatternAdapter() )->toArray( $pattern );

		expect( $out['post_types'] )->toBe( [ 'page', 'landing' ] );
	} );
} );

describe( 'ap.visualEditor.patternRender filter', function (): void {
	afterEach( function (): void {
		removeAllFilters( 'ap.visualEditor.patternRender' );
	} );

	it( 'lets a callback rewrite the rendered raw content', function (): void {
		addFilter( 'ap.visualEditor.patternRender', function ( string $html, string $slug, array $context ): string {
			return $html . '<!-- filtered:' . $slug . ' -->';
		}, 10, 3 );

		$out = ( new PatternAdapter() )->toArray( makeResolvedPattern() );

		expect( $out['content']['raw'] )->toBe( '<!-- wp:cover /--><!-- filtered:hero-banner -->' );
	} );

	it( 'passes source/synced/categories/block_types/post_types context so callbacks can gate per pattern shape', function (): void {
		$captured = null;
		addFilter( 'ap.visualEditor.patternRender', function ( string $html, string $slug, array $context ) use ( &$captured ): string {
			$captured = $context;

			return $html;
		}, 10, 3 );

		( new PatternAdapter() )->toArray( makeResolvedPattern( [
			'source'     => 'user',
			'synced'     => true,
			'categories' => [ 'hero', 'promo' ],
			'blockTypes' => [ 'core/cover', 'core/heading' ],
			'postTypes'  => [ 'page' ],
		] ) );

		expect( $captured )->not->toBeNull()
			->and( $captured['source'] )->toBe( 'user' )
			->and( $captured['synced'] )->toBeTrue()
			->and( $captured['categories'] )->toBe( [ 'hero', 'promo' ] )
			->and( $captured['block_types'] )->toBe( [ 'core/cover', 'core/heading' ] )
			->and( $captured['post_types'] )->toBe( [ 'page' ] );
	} );

	it( 'ignores a non-string filter return so the original content survives', function (): void {
		addFilter( 'ap.visualEditor.patternRender', function ( string $html ): ?array {
			return [ 'not', 'a', 'string' ];
		} );

		$out = ( new PatternAdapter() )->toArray( makeResolvedPattern() );

		expect( $out['content']['raw'] )->toBe( '<!-- wp:cover /-->' );
	} );
} );

describe( 'collection envelope', function (): void {
	it( 'returns a flat list in iteration order', function (): void {
		$patterns = [
			makeResolvedPattern( [ 'slug' => 'a' ] ),
			makeResolvedPattern( [ 'slug' => 'b' ] ),
		];

		$out = ( new PatternAdapter() )->collection( $patterns );

		expect( $out )->toHaveCount( 2 )
			->and( array_column( $out, 'slug' ) )->toBe( [ 'a', 'b' ] );
	} );

	it( 'returns an empty array for an empty iterable', function (): void {
		expect( ( new PatternAdapter() )->collection( [] ) )->toBe( [] );
	} );
} );
