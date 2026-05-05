<?php

/**
 * H6 MenuController integration tests.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

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
			->assertJsonPath( 'auto_add_pages', true );
	} );

	it( 'returns 404 when no menu matches the id', function (): void {
		$this->getJson( '/visual-editor/api/menus/9999' )->assertNotFound();
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
