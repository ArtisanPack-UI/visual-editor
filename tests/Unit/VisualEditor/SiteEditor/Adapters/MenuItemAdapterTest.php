<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\MenuItemAdapter;

function fullMenuItem(): array
{
	return [
		'id'          => 7,
		'parent_id'   => 0,
		'position'    => 2,
		'type'        => 'link',
		'label'       => 'About',
		'url'         => '/about',
		'target'      => '_blank',
		'classes'     => 'highlight has-icon',
		'rel'         => 'noopener',
		'description' => 'Company info',
		'object_type' => 'page',
		'object_id'   => 14,
		'kind'        => 'post-type',
	];
}

describe( 'single-record envelope', function (): void {
	it( 'shapes a fully-populated item into the wp_navigation_link envelope', function (): void {
		$out = ( new MenuItemAdapter() )->toArray( fullMenuItem(), 4 );

		expect( $out )->toMatchArray( [
			'id'          => 7,
			'menus'       => 4,
			'parent'      => 0,
			'position'    => 2,
			'type'        => 'link',
			'url'         => '/about',
			'target'      => '_blank',
			'description' => 'Company info',
			'object'      => 'page',
			'object_id'   => 14,
			'kind'        => 'post-type',
		] )
			->and( $out['title'] )->toBe( [ 'rendered' => 'About', 'raw' => 'About' ] )
			->and( $out['classes'] )->toBe( [ 'highlight', 'has-icon' ] )
			->and( $out['xfn'] )->toBe( [ 'noopener' ] );
	} );

	it( 'normalizes classes and rel from arrays as well as strings', function (): void {
		$item            = fullMenuItem();
		$item['classes'] = [ 'highlight', 'has-icon' ];
		$item['rel']     = [ 'noopener', 'nofollow' ];

		$out = ( new MenuItemAdapter() )->toArray( $item, 4 );

		expect( $out['classes'] )->toBe( [ 'highlight', 'has-icon' ] )
			->and( $out['xfn'] )->toBe( [ 'noopener', 'nofollow' ] );
	} );

	it( 'fills sensible defaults for missing optional fields', function (): void {
		$out = ( new MenuItemAdapter() )->toArray( [ 'label' => 'Home', 'url' => '/' ], 'primary' );

		expect( $out['menus'] )->toBe( 'primary' )
			->and( $out['parent'] )->toBe( 0 )
			->and( $out['position'] )->toBe( 0 )
			->and( $out['type'] )->toBe( 'link' )
			->and( $out['target'] )->toBe( '' )
			->and( $out['classes'] )->toBe( [] )
			->and( $out['xfn'] )->toBe( [] )
			->and( $out['description'] )->toBe( '' )
			->and( $out['object'] )->toBeNull()
			->and( $out['object_id'] )->toBeNull()
			->and( $out['kind'] )->toBeNull();
	} );

	it( 'falls back to an empty string id when neither int nor non-empty string is supplied', function (): void {
		$out = ( new MenuItemAdapter() )->toArray( [ 'label' => 'Home', 'url' => '/' ], 1 );

		expect( $out['id'] )->toBe( '' );
	} );
} );

describe( 'collection envelope', function (): void {
	it( 'flattens items in iteration order with the same parent menu id', function (): void {
		$items = [
			[ 'id' => 1, 'label' => 'Home', 'url' => '/' ],
			[ 'id' => 2, 'label' => 'Blog', 'url' => '/blog' ],
		];

		$out = ( new MenuItemAdapter() )->collection( $items, 9 );

		expect( $out )->toHaveCount( 2 )
			->and( array_column( $out, 'id' ) )->toBe( [ 1, 2 ] )
			->and( array_column( $out, 'menus' ) )->toBe( [ 9, 9 ] );
	} );

	it( 'returns an empty array for an empty iterable', function (): void {
		expect( ( new MenuItemAdapter() )->collection( [], 1 ) )->toBe( [] );
	} );
} );
