<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\MenuAdapter;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedMenu;

function makeResolvedMenu( array $overrides = [] ): ResolvedMenu
{
	$defaults = [
		'location' => 'primary',
		'name'     => 'Primary Navigation',
		'items'    => [
			[ 'label' => 'Home', 'url' => '/', 'type' => 'link' ],
			[ 'label' => 'About', 'url' => '/about', 'type' => 'link' ],
		],
		'wpId'     => null,
	];

	$args = array_merge( $defaults, $overrides );

	return new ResolvedMenu(
		location : $args['location'],
		name     : $args['name'],
		items    : $args['items'],
		wpId     : $args['wpId'],
	);
}

describe( 'single-record envelope', function (): void {
	it( 'uses the location key as slug and falls back to it as id when no DB row exists', function (): void {
		$out = ( new MenuAdapter() )->toArray( makeResolvedMenu() );

		expect( $out )->toMatchArray( [
			'id'     => 'primary',
			'slug'   => 'primary',
			'type'   => 'wp_navigation',
			'status' => 'publish',
		] )
			->and( $out['title'] )->toBe( [ 'rendered' => 'Primary Navigation', 'raw' => 'Primary Navigation' ] )
			->and( $out['items'] )->toHaveCount( 2 );
	} );

	it( 'surfaces wp_id when a menu record backs the location', function (): void {
		$out = ( new MenuAdapter() )->toArray( makeResolvedMenu( [ 'wpId' => 4 ] ) );

		expect( $out['id'] )->toBe( 4 )
			->and( $out['slug'] )->toBe( 'primary' );
	} );

	it( 'falls back from `wpId = 0` (no-record sentinel) to location for `id` (#438)', function (): void {
		$out = ( new MenuAdapter() )->toArray( makeResolvedMenu( [ 'wpId' => 0 ] ) );

		expect( $out['id'] )->toBe( 'primary' );
	} );

	it( 'forwards items as-is for the inspector to render without parse round-trip', function (): void {
		$items = [
			[ 'label' => 'Blog', 'url' => '/blog', 'type' => 'link', 'classes' => 'has-icon' ],
		];

		$out = ( new MenuAdapter() )->toArray( makeResolvedMenu( [ 'items' => $items ] ) );

		expect( $out['items'] )->toBe( $items );
	} );
} );

describe( 'collection envelope', function (): void {
	it( 'returns a flat list keyed by location order', function (): void {
		$menus = [
			makeResolvedMenu( [ 'location' => 'primary' ] ),
			makeResolvedMenu( [ 'location' => 'footer', 'name' => 'Footer' ] ),
		];

		$out = ( new MenuAdapter() )->collection( $menus );

		expect( $out )->toHaveCount( 2 )
			->and( array_column( $out, 'slug' ) )->toBe( [ 'primary', 'footer' ] )
			->and( $out[1]['title']['raw'] )->toBe( 'Footer' );
	} );

	it( 'returns an empty array for an empty iterable', function (): void {
		expect( ( new MenuAdapter() )->collection( [] ) )->toBe( [] );
	} );
} );
