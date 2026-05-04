<?php

/**
 * H6 MenuItemController integration tests.
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

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Menu item tester',
		'email'    => 'menu-items+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );

	$this->menu = Menu::create( [
		'theme' => 'digital-shopfront',
		'slug'  => 'primary',
		'name'  => 'Primary',
	] );
} );

describe( 'GET /visual-editor/api/menu-items', function (): void {
	it( 'returns 422 when menu_id is missing', function (): void {
		$this->getJson( '/visual-editor/api/menu-items' )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'menu_id' );
	} );

	it( 'lists items for a menu in (parent_id, position, id) order', function (): void {
		MenuItem::create( [
			'menu_id'  => $this->menu->id,
			'position' => 1,
			'type'     => 'link',
			'label'    => 'Home',
			'url'      => '/',
		] );

		MenuItem::create( [
			'menu_id'  => $this->menu->id,
			'position' => 0,
			'type'     => 'link',
			'label'    => 'Top',
			'url'      => '/top',
		] );

		$this->getJson( "/visual-editor/api/menu-items?menu_id={$this->menu->id}" )
			->assertOk()
			->assertJsonCount( 2 )
			->assertJsonPath( '0.title.raw', 'Top' )
			->assertJsonPath( '1.title.raw', 'Home' );
	} );

	it( 'returns an empty list when the menu has no items', function (): void {
		$this->getJson( "/visual-editor/api/menu-items?menu_id={$this->menu->id}" )
			->assertOk()
			->assertExactJson( [] );
	} );
} );

describe( 'GET /visual-editor/api/menu-items/{id}', function (): void {
	it( 'returns the WP-shape item envelope', function (): void {
		$item = MenuItem::create( [
			'menu_id'     => $this->menu->id,
			'position'    => 0,
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
		] );

		$this->getJson( "/visual-editor/api/menu-items/{$item->id}" )
			->assertOk()
			->assertJsonPath( 'id', $item->id )
			->assertJsonPath( 'menus', $this->menu->id )
			->assertJsonPath( 'type', 'link' )
			->assertJsonPath( 'title.raw', 'About' )
			->assertJsonPath( 'url', '/about' )
			->assertJsonPath( 'classes', [ 'highlight', 'has-icon' ] )
			->assertJsonPath( 'xfn', [ 'noopener' ] )
			->assertJsonPath( 'object_id', 14 )
			->assertJsonPath( 'kind', 'post-type' );
	} );

	it( 'returns 404 when no item matches', function (): void {
		$this->getJson( '/visual-editor/api/menu-items/9999' )->assertNotFound();
	} );
} );

describe( 'POST /visual-editor/api/menu-items', function (): void {
	it( 'creates an item and returns the WP shape', function (): void {
		$this->postJson( '/visual-editor/api/menu-items', [
			'menu_id'  => $this->menu->id,
			'type'     => 'link',
			'label'    => 'Blog',
			'url'      => '/blog',
			'position' => 5,
		] )
			->assertCreated()
			->assertJsonPath( 'menus', $this->menu->id )
			->assertJsonPath( 'title.raw', 'Blog' )
			->assertJsonPath( 'url', '/blog' )
			->assertJsonPath( 'position', 5 );

		expect( MenuItem::query()->where( 'label', 'Blog' )->exists() )->toBeTrue();
	} );

	it( 'defaults type to link when omitted', function (): void {
		$this->postJson( '/visual-editor/api/menu-items', [
			'menu_id' => $this->menu->id,
			'label'   => 'Default type',
			'url'     => '/',
		] )
			->assertCreated()
			->assertJsonPath( 'type', 'link' );
	} );

	it( 'rejects unknown link types at validation', function (): void {
		$this->postJson( '/visual-editor/api/menu-items', [
			'menu_id' => $this->menu->id,
			'type'    => 'spaceship',
			'label'   => 'Bad type',
			'url'     => '/',
		] )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'type' );
	} );
} );

describe( 'PUT /visual-editor/api/menu-items/{id}', function (): void {
	it( 'updates an existing item', function (): void {
		$item = MenuItem::create( [
			'menu_id'  => $this->menu->id,
			'position' => 0,
			'type'     => 'link',
			'label'    => 'Home',
			'url'      => '/',
		] );

		$this->putJson( "/visual-editor/api/menu-items/{$item->id}", [
			'label' => 'Home Renamed',
			'url'   => '/home',
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'Home Renamed' )
			->assertJsonPath( 'url', '/home' );
	} );

	it( 'returns 404 when the id does not exist', function (): void {
		$this->putJson( '/visual-editor/api/menu-items/9999', [ 'label' => 'Nope' ] )->assertNotFound();
	} );
} );

describe( 'DELETE /visual-editor/api/menu-items/{id}', function (): void {
	it( 'deletes the item and returns 204', function (): void {
		$item = MenuItem::create( [
			'menu_id'  => $this->menu->id,
			'position' => 0,
			'type'     => 'link',
			'label'    => 'Home',
			'url'      => '/',
		] );

		$this->deleteJson( "/visual-editor/api/menu-items/{$item->id}" )->assertNoContent();

		expect( MenuItem::query()->where( 'id', $item->id )->exists() )->toBeFalse();
	} );

	it( 'returns 404 when no item matches', function (): void {
		$this->deleteJson( '/visual-editor/api/menu-items/9999' )->assertNotFound();
	} );
} );
