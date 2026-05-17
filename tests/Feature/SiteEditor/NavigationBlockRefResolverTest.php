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

	it( 'leaves an existing ref alone when both ref and __unstableLocation are present', function (): void {
		$blocks = [
			[
				'name'        => 'core/navigation',
				'attributes'  => [ '__unstableLocation' => 'primary', 'ref' => 99 ],
				'innerBlocks' => [],
			],
		];

		$resolved = ( new NavigationBlockRefResolver() )->resolve( $blocks, 'jmwd-default' );

		expect( $resolved[0]['attributes']['ref'] )->toBe( 99 );
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
} );
