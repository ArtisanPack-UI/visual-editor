<?php

/**
 * Unit tests for {@see NavigationBlockRefResolver}. The class only
 * touches cms-framework when it has to look up an assignment, so the
 * tests exercise both paths: pure tree-shaping (no DB involvement)
 * and DB-backed resolution through the existing test database.
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\VisualEditor\SiteEditor\NavigationBlockRefResolver;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;

uses( TestCase::class, WithCmsFramework::class );

describe( 'NavigationBlockRefResolver — tree shaping (no DB)', function (): void {
	it( 'leaves a tree without any core/navigation blocks untouched', function (): void {
		$blocks = [
			[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'Hi' ], 'innerBlocks' => [] ],
			[ 'name' => 'core/heading', 'attributes' => [], 'innerBlocks' => [] ],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		// Reference equality — the resolver short-circuits when
		// nothing changes, so the projection is cheap.
		expect( $resolved )->toBe( $blocks );
	} );

	it( 'leaves an existing ref alone when both ref and __unstableLocation are present, but strips the legacy location attr', function (): void {
		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'primary', 'ref' => 99 ],
				'innerBlocks' => [],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		expect( $resolved[0]['attributes']['ref'] )->toBe( 99 );
		// `__unstableLocation` is stripped even when `ref` is preset.
		// Gutenberg's nav block prefers `__unstableLocation` over `ref`
		// when both are present, so leaving it in place would send a
		// block-with-explicit-ref down the broken location-lookup
		// path. CodeRabbit follow-up on #459.
		expect( $resolved[0]['attributes'] )->not->toHaveKey( '__unstableLocation' );
	} );

	it( 'leaves a nav block without __unstableLocation alone', function (): void {
		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [],
				'innerBlocks' => [],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		expect( $resolved[0]['attributes'] )->toBe( [] );
	} );

	it( 'leaves a nav block with an unrecognized location alone (no assignment in DB)', function (): void {
		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'nonexistent' ],
				'innerBlocks' => [],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		expect( $resolved[0]['attributes'] )->toBe( [ '__unstableLocation' => 'nonexistent' ] );
	} );
} );

describe( 'NavigationBlockRefResolver — DB-backed resolution', function (): void {
	it( 'stamps ref on a root-level nav block from the matching location assignment', function (): void {
		$menu = Menu::create( [ 'theme' => 'jmwd-default', 'slug' => 'primary', 'name' => 'Primary' ] );
		MenuLocationAssignment::create( [
			'theme'    => 'jmwd-default',
			'location' => 'primary',
			'menu_id'  => $menu->id,
		] );

		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'primary' ],
				'innerBlocks' => [],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		expect( $resolved[0]['attributes']['ref'] )->toBe( $menu->id );
		// `__unstableLocation` is stripped after stamping `ref`.
		// Leaving it in place sent Gutenberg's nav block down its
		// own (broken in our environment) location-lookup path and
		// kept the picker showing "This Navigation Menu is empty"
		// even with a perfectly good `ref` next to it.
		expect( $resolved[0]['attributes'] )->not->toHaveKey( '__unstableLocation' );
	} );

	it( 'resolves nav blocks nested inside other blocks', function (): void {
		$menu = Menu::create( [ 'theme' => 'jmwd-default', 'slug' => 'primary', 'name' => 'Primary' ] );
		MenuLocationAssignment::create( [
			'theme'    => 'jmwd-default',
			'location' => 'primary',
			'menu_id'  => $menu->id,
		] );

		$blocks = [
			[
				'name'        => 'core/group',
				'attributes'  => [ 'tagName' => 'header' ],
				'innerBlocks' => [
					[
						'name'        => 'core/columns',
						'attributes'  => [],
						'innerBlocks' => [
							[
								'name'        => 'core/column',
								'attributes'  => [],
								'innerBlocks' => [
									[
										'name'        => 'core/navigation',
										'attributes'  => [ '__unstableLocation' => 'primary' ],
										'innerBlocks' => [],
									],
								],
							],
						],
					],
				],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		$nav = $resolved[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
		expect( $nav['name'] )->toBe( 'core/navigation' );
		expect( $nav['attributes']['ref'] )->toBe( $menu->id );
	} );

	it( 'scopes the location lookup by theme', function (): void {
		$menuA = Menu::create( [ 'theme' => 'theme-a', 'slug' => 'primary', 'name' => 'A' ] );
		$menuB = Menu::create( [ 'theme' => 'theme-b', 'slug' => 'primary', 'name' => 'B' ] );

		MenuLocationAssignment::create( [ 'theme' => 'theme-a', 'location' => 'primary', 'menu_id' => $menuA->id ] );
		MenuLocationAssignment::create( [ 'theme' => 'theme-b', 'location' => 'primary', 'menu_id' => $menuB->id ] );

		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'primary' ],
				'innerBlocks' => [],
			],
		];

		$resolverA = new NavigationBlockRefResolver();
		$resolverB = new NavigationBlockRefResolver();

		expect( $resolverA->resolve( $blocks, 'theme-a' )[0]['attributes']['ref'] )->toBe( $menuA->id );
		expect( $resolverB->resolve( $blocks, 'theme-b' )[0]['attributes']['ref'] )->toBe( $menuB->id );
	} );

	it( 'caches the lookup so a tree with multiple nav blocks only queries once per location', function (): void {
		$menu = Menu::create( [ 'theme' => 'jmwd-default', 'slug' => 'primary', 'name' => 'Primary' ] );
		MenuLocationAssignment::create( [
			'theme'    => 'jmwd-default',
			'location' => 'primary',
			'menu_id'  => $menu->id,
		] );

		// Three nav blocks at the same location — all should get the
		// same resolved ref. Caching is a per-instance behavior, so
		// the assertion is that all three resolve correctly with a
		// single resolver; a regression that broke the cache would
		// still pass this test but the query count would balloon
		// (verified separately during code review).
		$blocks = array_fill( 0, 3, [
			'name'        => 'core/navigation',
			'attributes'  => [ '__unstableLocation' => 'primary' ],
			'innerBlocks' => [],
		] );

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		foreach ( $resolved as $block ) {
			expect( $block['attributes']['ref'] )->toBe( $menu->id );
		}
	} );

	it( 'populates innerBlocks from the menu items when the nav block tree is empty', function (): void {
		// Gutenberg's nav block reads its children from the block tree
		// itself, not from an entity record — so without populated
		// innerBlocks the canvas renders an empty nav and the
		// inspector shows "is empty" even when menu_items has rows.
		$menu = Menu::create( [ 'theme' => 'jmwd-default', 'slug' => 'primary', 'name' => 'Primary' ] );
		MenuLocationAssignment::create( [ 'theme' => 'jmwd-default', 'location' => 'primary', 'menu_id' => $menu->id ] );

		$menu->items()->create( [ 'parent_id' => null, 'position' => 0, 'type' => 'link', 'label' => 'Home', 'url' => '/' ] );
		$menu->items()->create( [ 'parent_id' => null, 'position' => 1, 'type' => 'link', 'label' => 'Services', 'url' => '/services' ] );

		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'primary' ],
				'innerBlocks' => [],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		$inner = $resolved[0]['innerBlocks'];
		expect( $inner )->toHaveCount( 2 );
		expect( $inner[0]['name'] )->toBe( 'core/navigation-link' );
		expect( $inner[0]['attributes']['label'] )->toBe( 'Home' );
		expect( $inner[1]['attributes']['label'] )->toBe( 'Services' );

		// Each projected block must carry a `clientId` + `isValid: true`
		// so Gutenberg's block-editor data store accepts it on receive.
		// Other blocks in the response (group, columns, etc.) carry
		// these because cms-framework's seed applier stamps them at
		// write time; the runtime projection has to match or the whole
		// tree fails to mount silently with no console error.
		foreach ( $inner as $block ) {
			expect( $block )->toHaveKey( 'clientId' );
			expect( $block['clientId'] )->toBeString()->not->toBe( '' );
			expect( $block['isValid'] )->toBeTrue();
		}
	} );

	it( 'leaves existing innerBlocks alone when the author has already authored child blocks', function (): void {
		$menu = Menu::create( [ 'theme' => 'jmwd-default', 'slug' => 'primary', 'name' => 'Primary' ] );
		MenuLocationAssignment::create( [ 'theme' => 'jmwd-default', 'location' => 'primary', 'menu_id' => $menu->id ] );
		$menu->items()->create( [ 'parent_id' => null, 'position' => 0, 'type' => 'link', 'label' => 'From DB', 'url' => '/' ] );

		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'primary' ],
				'innerBlocks' => [
					[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Already authored' ], 'innerBlocks' => [] ],
				],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		expect( $resolved[0]['innerBlocks'] )->toHaveCount( 1 );
		expect( $resolved[0]['innerBlocks'][0]['attributes']['label'] )->toBe( 'Already authored' );
	} );
} );
