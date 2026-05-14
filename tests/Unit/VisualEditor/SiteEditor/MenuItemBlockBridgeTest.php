<?php

/**
 * Unit tests for {@see MenuItemBlockBridge} — the PHP mirror of
 * `menu-tree.ts`. Pure converter, no DB: `itemsToBlocks()` is fed
 * plain row-shaped objects, `blocksToItemSpecs()` plain arrays.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\MenuItemBlockBridge;

/**
 * Build a `menu_items`-row-shaped object for the read-path tests.
 *
 * @param  array<string, mixed>  $overrides
 */
function menuItemRow( array $overrides = [] ): object
{
	return (object) array_merge( [
		'id'          => 1,
		'parent_id'   => null,
		'position'    => 0,
		'type'        => 'link',
		'label'       => 'Item',
		'url'         => null,
		'target'      => '_self',
		'rel'         => null,
		'classes'     => null,
		'kind'        => null,
		'object_type' => null,
		'object_id'   => null,
	], $overrides );
}

describe( 'itemsToBlocks() — read path', function (): void {
	it( 'emits a childless row as core/navigation-link', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'label' => 'Home', 'url' => '/' ] ),
		] );

		expect( $blocks )->toHaveCount( 1 )
			->and( $blocks[0]['name'] )->toBe( 'core/navigation-link' )
			->and( $blocks[0]['attributes']['label'] )->toBe( 'Home' )
			->and( $blocks[0]['attributes']['url'] )->toBe( '/' )
			->and( $blocks[0]['innerBlocks'] )->toBe( [] );
	} );

	it( 'emits a row with children as core/navigation-submenu, nesting recursively', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'parent_id' => null, 'label' => 'About' ] ),
			menuItemRow( [ 'id' => 2, 'parent_id' => 1, 'label' => 'Team' ] ),
		] );

		expect( $blocks )->toHaveCount( 1 )
			->and( $blocks[0]['name'] )->toBe( 'core/navigation-submenu' )
			->and( $blocks[0]['attributes']['label'] )->toBe( 'About' )
			->and( $blocks[0]['innerBlocks'] )->toHaveCount( 1 )
			->and( $blocks[0]['innerBlocks'][0]['name'] )->toBe( 'core/navigation-link' )
			->and( $blocks[0]['innerBlocks'][0]['attributes']['label'] )->toBe( 'Team' );
	} );

	it( 'is children-driven — a childless submenu-typed row still emits a link', function (): void {
		// Matches `menu-tree.ts`, which collapses childless submenus to
		// links so the round-trip is stable.
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'type' => 'submenu', 'label' => 'Empty' ] ),
		] );

		expect( $blocks[0]['name'] )->toBe( 'core/navigation-link' );
	} );

	it( 'maps the typed-reference + target columns onto block attributes', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [
				'id'          => 1,
				'label'       => 'Blog',
				'url'         => '/blog',
				'target'      => '_blank',
				'rel'         => 'noopener',
				'classes'     => 'is-cta',
				'kind'        => 'post-type',
				'object_type' => 'page',
				'object_id'   => 42,
			] ),
		] );

		$attrs = $blocks[0]['attributes'];
		expect( $attrs['kind'] )->toBe( 'post-type' )
			->and( $attrs['type'] )->toBe( 'page' )
			->and( $attrs['id'] )->toBe( 42 )
			->and( $attrs['opensInNewTab'] )->toBeTrue()
			->and( $attrs['rel'] )->toBe( 'noopener' )
			->and( $attrs['className'] )->toBe( 'is-cta' );
	} );

	it( 'omits empty optional attributes — only label is always present', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'label' => 'Bare', 'target' => '_self' ] ),
		] );

		expect( $blocks[0]['attributes'] )->toBe( [ 'label' => 'Bare' ] );
	} );

	it( 'normalizes sibling order by (position, id) regardless of input order', function (): void {
		// The bridge no longer depends on the caller pre-sorting — it
		// orders each parent bucket itself. Feed the rows shuffled.
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 9, 'position' => 2, 'label' => 'Third' ] ),
			menuItemRow( [ 'id' => 3, 'position' => 0, 'label' => 'First' ] ),
			menuItemRow( [ 'id' => 7, 'position' => 1, 'label' => 'Second' ] ),
		] );

		expect( array_column( array_column( $blocks, 'attributes' ), 'label' ) )
			->toBe( [ 'First', 'Second', 'Third' ] );
	} );

	it( 'does not infinite-loop on a cyclic parent_id chain', function (): void {
		// Corrupt data — reachable via the /menu-items endpoint, which
		// accepts an arbitrary parent_id. Item 1's parent is 2 and
		// item 2's parent is 1. The cycle guard must bottom out.
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'parent_id' => 2, 'label' => 'A' ] ),
			menuItemRow( [ 'id' => 2, 'parent_id' => 1, 'label' => 'B' ] ),
		] );

		// Neither item is reachable from the root (both have a non-zero
		// parent), so the projection is simply empty — and crucially,
		// it returns rather than recursing forever.
		expect( $blocks )->toBe( [] );
	} );
} );

describe( 'blocksToItemSpecs() — write path', function (): void {
	it( 'parses a link block into a row spec', function (): void {
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
				'innerBlocks' => [],
			],
		] );

		expect( $specs )->toHaveCount( 1 )
			->and( $specs[0]['attributes']['type'] )->toBe( 'link' )
			->and( $specs[0]['attributes']['label'] )->toBe( 'Home' )
			->and( $specs[0]['attributes']['url'] )->toBe( '/' )
			->and( $specs[0]['attributes']['target'] )->toBe( '_self' )
			->and( $specs[0]['children'] )->toBe( [] );
	} );

	it( 'parses a submenu block, marking type=submenu and nesting children', function (): void {
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[
				'name'        => 'core/navigation-submenu',
				'attributes'  => [ 'label' => 'About' ],
				'innerBlocks' => [
					[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Team' ], 'innerBlocks' => [] ],
				],
			],
		] );

		expect( $specs[0]['attributes']['type'] )->toBe( 'submenu' )
			->and( $specs[0]['children'] )->toHaveCount( 1 )
			->and( $specs[0]['children'][0]['attributes']['type'] )->toBe( 'link' )
			->and( $specs[0]['children'][0]['attributes']['label'] )->toBe( 'Team' );
	} );

	it( 'drops blocks it does not translate', function (): void {
		// Matches `menu-tree.ts` — the native tree editor has no UI for
		// block types it doesn't understand.
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'nope' ], 'innerBlocks' => [] ],
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Kept' ], 'innerBlocks' => [] ],
			'not-an-array',
		] );

		expect( $specs )->toHaveCount( 1 )
			->and( $specs[0]['attributes']['label'] )->toBe( 'Kept' );
	} );

	it( 'maps opensInNewTab + typed-reference attributes onto columns', function (): void {
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [
					'label'         => 'Blog',
					'kind'          => 'post-type',
					'type'          => 'page',
					'id'            => 42,
					'opensInNewTab' => true,
					'className'     => 'is-cta',
				],
				'innerBlocks' => [],
			],
		] );

		$attrs = $specs[0]['attributes'];
		expect( $attrs['target'] )->toBe( '_blank' )
			->and( $attrs['kind'] )->toBe( 'post-type' )
			->and( $attrs['object_type'] )->toBe( 'page' )
			->and( $attrs['object_id'] )->toBe( 42 )
			->and( $attrs['classes'] )->toBe( 'is-cta' );
	} );

	it( 'normalizes a non-numeric attributes.id to a null object_id', function (): void {
		// `menu-tree.ts` can emit `id` as a slug string; `object_id` is
		// an unsigned bigint, so only a numeric positive value maps.
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'X', 'id' => 'a-slug' ], 'innerBlocks' => [] ],
		] );

		expect( $specs[0]['attributes']['object_id'] )->toBeNull();
	} );
} );
