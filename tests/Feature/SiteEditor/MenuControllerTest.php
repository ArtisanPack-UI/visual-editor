<?php

/**
 * H6 MenuController integration tests.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

/**
 * Builds a `core/navigation-link` block — the leaf shape the site
 * editor's `menuTreeToBlocks` emits.
 *
 * @param  array<string, mixed>  $attributes
 */
function navLink( array $attributes ): array
{
	return [
		'name'        => 'core/navigation-link',
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

/**
 * Builds a `core/navigation-submenu` block wrapping the given children.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<int, mixed>  $innerBlocks
 */
function navSubmenu( array $attributes, array $innerBlocks ): array
{
	return [
		'name'        => 'core/navigation-submenu',
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Menu tester',
		'email'    => 'menus+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

describe( 'GET /visual-editor/api/menus', function (): void {
	it( 'returns an empty list when no menus exist', function (): void {
		$this->getJson( '/visual-editor/api/menus' )
			->assertOk()
			->assertExactJson( [] );
	} );

	it( 'lists all menus across themes by default', function (): void {
		Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );
		Menu::create( [ 'theme' => 'other-theme', 'slug' => 'primary', 'name' => 'Other Primary' ] );

		$this->getJson( '/visual-editor/api/menus' )
			->assertOk()
			->assertJsonCount( 2 )
			->assertJsonPath( '0.type', 'wp_navigation' );
	} );

	it( 'filters by theme via ?theme query param', function (): void {
		Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );
		Menu::create( [ 'theme' => 'other-theme', 'slug' => 'primary', 'name' => 'Other' ] );

		$this->getJson( '/visual-editor/api/menus?theme=other-theme' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.theme', 'other-theme' );
	} );
} );

describe( 'GET /visual-editor/api/menus/{id}', function (): void {
	it( 'returns the menu by id with the WP-shape envelope', function (): void {
		$menu = Menu::create( [
			'theme'          => 'digital-shopfront',
			'slug'           => 'primary',
			'name'           => 'Primary Navigation',
			'description'    => 'Top of every page',
			'auto_add_pages' => true,
		] );

		$this->getJson( "/visual-editor/api/menus/{$menu->id}" )
			->assertOk()
			->assertJsonPath( 'id', $menu->id )
			->assertJsonPath( 'slug', 'primary' )
			->assertJsonPath( 'name', 'Primary Navigation' )
			->assertJsonPath( 'title.rendered', 'Primary Navigation' )
			->assertJsonPath( 'type', 'wp_navigation' )
			->assertJsonPath( 'auto_add_pages', true )
			// #438. NavigationBrowser dereferences `row.content.blocks`
			// — the shape must always include an envelope, even empty.
			->assertJsonPath( 'content.raw', '' )
			->assertJsonPath( 'content.blocks', [] );
	} );

	it( 'returns 404 when no menu matches the id', function (): void {
		$this->getJson( '/visual-editor/api/menus/9999' )->assertNotFound();
	} );

	it( 'populates content.raw with the serialized block-comment tree when items exist (Keystone #48)', function (): void {
		// Gutenberg's `core/navigation` block reads `content.raw` (not
		// `content.blocks`) when deciding which inner blocks to render
		// inside its picker. Pin the serialized form so a future
		// refactor doesn't accidentally regress the picker back to
		// empty-list state.
		$menu = Menu::create( [
			'theme' => 'digital-shopfront',
			'slug'  => 'primary',
			'name'  => 'Primary',
		] );

		$menu->items()->create( [
			'parent_id' => null,
			'position'  => 0,
			'type'      => 'link',
			'label'     => 'Home',
			'url'       => '/',
		] );

		$menu->items()->create( [
			'parent_id' => null,
			'position'  => 1,
			'type'      => 'link',
			'label'     => 'Contact',
			'url'       => '/contact',
		] );

		$response = $this->getJson( "/visual-editor/api/menus/{$menu->id}" )->assertOk();

		$raw = $response->json( 'content.raw' );

		expect( $raw )->toContain( 'wp:navigation-link' );
		expect( $raw )->toContain( '"label":"Home"' );
		expect( $raw )->toContain( '"label":"Contact"' );
		// Items render in `(position, id)` order — same as the front-end.
		expect( strpos( $raw, '"Home"' ) )->toBeLessThan( strpos( $raw, '"Contact"' ) );
	} );
} );

describe( 'POST /visual-editor/api/menus', function (): void {
	it( 'creates a menu and returns the WP shape', function (): void {
		$this->postJson( '/visual-editor/api/menus', [
			'theme' => 'digital-shopfront',
			'slug'  => 'primary',
			'name'  => 'Primary',
		] )
			->assertCreated()
			->assertJsonPath( 'slug', 'primary' )
			->assertJsonPath( 'name', 'Primary' );

		expect( Menu::query()->where( 'slug', 'primary' )->exists() )->toBeTrue();
	} );

	it( 'returns 409 on duplicate (theme, slug)', function (): void {
		Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->postJson( '/visual-editor/api/menus', [
			'theme' => 'digital-shopfront',
			'slug'  => 'primary',
			'name'  => 'Duplicate',
		] )->assertStatus( 409 );
	} );

	it( 'returns 422 when slug is missing (theme falls back to active)', function (): void {
		$this->postJson( '/visual-editor/api/menus', [ 'name' => 'Missing slug' ] )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'slug' );
	} );

	// #438. The editor's create-menu dialog sends `title`, not `name`,
	// and never sends `theme`. The controller maps `title` → `name`
	// and falls back to ThemeManager's active theme.
	it( 'accepts the WP-shape `title` field and falls back to the active theme (#438)', function (): void {
		$this->postJson( '/visual-editor/api/menus', [
			'slug'  => 'primary',
			'title' => 'Primary Navigation',
		] )
			->assertCreated()
			->assertJsonPath( 'theme', 'digital-shopfront' )
			->assertJsonPath( 'name', 'Primary Navigation' );

		expect( Menu::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'primary' )->first()?->name )
			->toBe( 'Primary Navigation' );
	} );

	it( 'returns 422 when theme is missing and no active theme is bound', function (): void {
		$this->mock( \ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );

		$this->postJson( '/visual-editor/api/menus', [
			'slug'  => 'primary',
			'title' => 'Primary',
		] )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'theme' );
	} );
} );

describe( 'PUT /visual-editor/api/menus/{id}', function (): void {
	it( 'updates an existing menu', function (): void {
		$menu = Menu::create( [
			'theme' => 'digital-shopfront',
			'slug'  => 'primary',
			'name'  => 'Primary',
		] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'name'           => 'Primary Renamed',
			'description'    => 'Updated description',
			'auto_add_pages' => true,
		] )
			->assertOk()
			->assertJsonPath( 'name', 'Primary Renamed' )
			->assertJsonPath( 'description', 'Updated description' )
			->assertJsonPath( 'auto_add_pages', true );
	} );

	it( 'returns 404 when the id does not exist', function (): void {
		$this->putJson( '/visual-editor/api/menus/9999', [ 'name' => 'Nope' ] )->assertNotFound();
	} );

	it( 'leaves the theme untouched on a partial update that omits it (#438)', function (): void {
		// The active-theme fallback in modelAttributesFromRequest() is
		// create-only. A partial update — here a rename — must not
		// silently re-home a menu belonging to a non-active theme onto
		// the active one.
		$menu = Menu::create( [
			'theme' => 'other-theme',
			'slug'  => 'primary',
			'name'  => 'Primary',
		] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'name' => 'Primary Renamed',
		] )
			->assertOk()
			->assertJsonPath( 'name', 'Primary Renamed' )
			->assertJsonPath( 'theme', 'other-theme' );

		expect( $menu->fresh()->theme )->toBe( 'other-theme' );
	} );

	it( 'returns 409 when the slug update would collide with another menu in the same theme', function (): void {
		Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );
		$secondary = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'secondary', 'name' => 'Secondary' ] );

		$this->putJson( "/visual-editor/api/menus/{$secondary->id}", [
			'slug' => 'primary',
		] )->assertStatus( 409 );
	} );
} );

describe( 'DELETE /visual-editor/api/menus/{id}', function (): void {
	it( 'deletes the menu and returns 204', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->deleteJson( "/visual-editor/api/menus/{$menu->id}" )->assertNoContent();

		expect( Menu::query()->where( 'id', $menu->id )->exists() )->toBeFalse();
	} );

	it( 'returns 404 when no menu matches', function (): void {
		$this->deleteJson( '/visual-editor/api/menus/9999' )->assertNotFound();
	} );
} );

describe( 'navigation tree round-trip — content.blocks ↔ menu_items (#440)', function (): void {
	it( 'persists a PUT content.blocks tree to menu_items with correct parent_id + position', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		// 2 top-level items + 1 nested under the second.
		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [
				'raw'    => '',
				'blocks' => [
					navLink( [ 'label' => 'Home', 'url' => '/' ] ),
					navSubmenu(
						[ 'label' => 'About', 'url' => '/about' ],
						[ navLink( [ 'label' => 'Team', 'url' => '/about/team' ] ) ],
					),
				],
			],
		] )->assertOk();

		$rows = MenuItem::query()->where( 'menu_id', $menu->id )
			->orderByRaw( 'COALESCE(parent_id, 0)' )->orderBy( 'position' )->orderBy( 'id' )->get();

		expect( $rows )->toHaveCount( 3 );

		// Two roots, in order.
		$roots = $rows->whereNull( 'parent_id' )->values();
		expect( $roots )->toHaveCount( 2 )
			->and( $roots[0]->label )->toBe( 'Home' )
			->and( $roots[0]->type )->toBe( 'link' )
			->and( $roots[0]->position )->toBe( 0 )
			->and( $roots[1]->label )->toBe( 'About' )
			->and( $roots[1]->type )->toBe( 'submenu' )
			->and( $roots[1]->position )->toBe( 1 );

		// The nested child points at the "About" submenu, position 0.
		$child = $rows->whereNotNull( 'parent_id' )->first();
		expect( $child->label )->toBe( 'Team' )
			->and( $child->parent_id )->toBe( $roots[1]->id )
			->and( $child->position )->toBe( 0 );
	} );

	it( 'round-trips the same tree back through GET after a PUT', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$tree = [
			navLink( [ 'label' => 'Home', 'url' => '/' ] ),
			navSubmenu(
				[ 'label' => 'About', 'url' => '/about' ],
				[ navLink( [ 'label' => 'Team', 'url' => '/about/team' ] ) ],
			),
		];

		// The PUT response and a fresh GET must both project the items
		// back into the same block tree — the editor rebuilds its tree
		// from the response without a re-fetch.
		$putResponse = $this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [ 'raw' => '', 'blocks' => $tree ],
		] )->assertOk();

		foreach ( [ $putResponse->json( 'content.blocks' ), $this->getJson( "/visual-editor/api/menus/{$menu->id}" )->json( 'content.blocks' ) ] as $blocks ) {
			expect( $blocks )->toHaveCount( 2 )
				->and( $blocks[0]['name'] )->toBe( 'core/navigation-link' )
				->and( $blocks[0]['attributes']['label'] )->toBe( 'Home' )
				->and( $blocks[1]['name'] )->toBe( 'core/navigation-submenu' )
				->and( $blocks[1]['attributes']['label'] )->toBe( 'About' )
				->and( $blocks[1]['innerBlocks'] )->toHaveCount( 1 )
				->and( $blocks[1]['innerBlocks'][0]['name'] )->toBe( 'core/navigation-link' )
				->and( $blocks[1]['innerBlocks'][0]['attributes']['label'] )->toBe( 'Team' );
		}
	} );

	it( 'maps typed-reference and target attributes onto the menu_items columns', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [
				'raw'    => '',
				'blocks' => [
					navLink( [
						'label'         => 'Blog',
						'url'           => '/blog',
						'kind'          => 'post-type',
						'type'          => 'page',
						'id'            => 42,
						'opensInNewTab' => true,
						'rel'           => 'noopener',
						'className'     => 'is-highlighted',
					] ),
				],
			],
		] )->assertOk();

		$row = MenuItem::query()->where( 'menu_id', $menu->id )->firstOrFail();
		expect( $row->kind )->toBe( 'post-type' )
			->and( $row->object_type )->toBe( 'page' )
			->and( $row->object_id )->toBe( 42 )
			->and( $row->target )->toBe( '_blank' )
			->and( $row->rel )->toBe( 'noopener' )
			->and( $row->classes )->toBe( 'is-highlighted' );

		// …and they round-trip back out as block attributes.
		$attrs = $this->getJson( "/visual-editor/api/menus/{$menu->id}" )->json( 'content.blocks.0.attributes' );
		expect( $attrs['kind'] )->toBe( 'post-type' )
			->and( $attrs['type'] )->toBe( 'page' )
			->and( $attrs['id'] )->toBe( 42 )
			->and( $attrs['opensInNewTab'] )->toBeTrue();
	} );

	it( 'deletes rows the new tree no longer references', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [ 'raw' => '', 'blocks' => [
				navLink( [ 'label' => 'Home', 'url' => '/' ] ),
				navLink( [ 'label' => 'Contact', 'url' => '/contact' ] ),
			] ],
		] )->assertOk();
		expect( MenuItem::query()->where( 'menu_id', $menu->id )->count() )->toBe( 2 );

		// Re-save with only the first item — the dropped row is gone.
		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [ 'raw' => '', 'blocks' => [
				navLink( [ 'label' => 'Home', 'url' => '/' ] ),
			] ],
		] )->assertOk();

		$rows = MenuItem::query()->where( 'menu_id', $menu->id )->get();
		expect( $rows )->toHaveCount( 1 )
			->and( $rows->first()->label )->toBe( 'Home' );
	} );

	it( 'reorders siblings — positions reflect the new tree order', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [ 'raw' => '', 'blocks' => [
				navLink( [ 'label' => 'First', 'url' => '/1' ] ),
				navLink( [ 'label' => 'Second', 'url' => '/2' ] ),
			] ],
		] )->assertOk();

		// Swap the order.
		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [ 'raw' => '', 'blocks' => [
				navLink( [ 'label' => 'Second', 'url' => '/2' ] ),
				navLink( [ 'label' => 'First', 'url' => '/1' ] ),
			] ],
		] )->assertOk();

		$byLabel = MenuItem::query()->where( 'menu_id', $menu->id )->get()->keyBy( 'label' );
		expect( $byLabel['Second']->position )->toBe( 0 )
			->and( $byLabel['First']->position )->toBe( 1 );
	} );

	it( 'leaves existing items untouched on a partial update that omits content', function (): void {
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [ 'raw' => '', 'blocks' => [ navLink( [ 'label' => 'Home', 'url' => '/' ] ) ] ],
		] )->assertOk();

		// A rename with no `content` key must not wipe the items.
		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [ 'name' => 'Renamed' ] )->assertOk();

		expect( MenuItem::query()->where( 'menu_id', $menu->id )->count() )->toBe( 1 );
	} );

	it( 'accepts content as a serialized block-comment string (Gutenberg default save path — Keystone #48)', function (): void {
		// Gutenberg's `wp_navigation` save flow sends `content` as a
		// string of WP block-comment markup, not the `{ raw, blocks }`
		// object shape #440 uses. The controller has to parse it back
		// into a tree before routing through replaceMenuItems —
		// otherwise the editor's "Save" never persists nav-block edits.
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$serialized = "<!-- wp:navigation-link {\"label\":\"Home\",\"url\":\"/\"} /-->\n"
			. "<!-- wp:navigation-submenu {\"label\":\"About\"} -->\n"
			. "<!-- wp:navigation-link {\"label\":\"Team\",\"url\":\"/team\"} /-->\n"
			. '<!-- /wp:navigation-submenu -->';

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => $serialized,
		] )->assertOk();

		$items = MenuItem::query()->where( 'menu_id', $menu->id )->orderBy( 'position' )->get();

		expect( $items )->toHaveCount( 3 );

		$home = $items->firstWhere( 'label', 'Home' );
		expect( $home->parent_id )->toBeNull();
		expect( $home->url )->toBe( '/' );
		expect( $home->position )->toBe( 0 );

		$about = $items->firstWhere( 'label', 'About' );
		expect( $about->parent_id )->toBeNull();
		expect( $about->position )->toBe( 1 );

		$team = $items->firstWhere( 'label', 'Team' );
		expect( $team->parent_id )->toBe( $about->id );
		expect( $team->url )->toBe( '/team' );
	} );

	it( 'still accepts the legacy content.blocks array shape (#440 path)', function (): void {
		// Backward compat: the array-shape save path keeps working for
		// clients that send `{ raw, blocks }` directly (our own tests,
		// future first-party flows, etc.).
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
			'content' => [
				'raw'    => '',
				'blocks' => [ navLink( [ 'label' => 'Home', 'url' => '/' ] ) ],
			],
		] )->assertOk();

		expect( MenuItem::query()->where( 'menu_id', $menu->id )->where( 'label', 'Home' )->exists() )->toBeTrue();
	} );

	it( 'replaces items wholesale when an empty string content is sent', function (): void {
		// An empty serialized string represents "no items" — same as
		// `content.blocks: []`. Should wipe the table for the menu.
		$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );
		$menu->items()->create( [ 'parent_id' => null, 'position' => 0, 'type' => 'link', 'label' => 'Old', 'url' => '/' ] );

		$this->putJson( "/visual-editor/api/menus/{$menu->id}", [ 'content' => '' ] )->assertOk();

		expect( MenuItem::query()->where( 'menu_id', $menu->id )->count() )->toBe( 0 );
	} );
} );
