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

	it( 'preserves empty category and block_type lists', function (): void {
		$pattern = makeResolvedPattern( [ 'categories' => [], 'blockTypes' => [] ] );

		$out = ( new PatternAdapter() )->toArray( $pattern );

		expect( $out['categories'] )->toBe( [] )
			->and( $out['block_types'] )->toBe( [] );
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
