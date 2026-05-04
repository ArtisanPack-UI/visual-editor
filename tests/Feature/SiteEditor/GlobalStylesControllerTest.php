<?php

/**
 * H6 GlobalStylesController integration tests — runs the visual-editor
 * REST surface against a booted cms-framework.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Global styles tester',
		'email'    => 'global-styles+' . uniqid() . '@example.com',
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

function rebuildSiteEditorResolversForGlobalStylesTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'GET /visual-editor/api/global-styles/lookup', function (): void {
	it( 'returns __base__ when no DB row exists yet', function (): void {
		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/lookup' )
			->assertOk()
			->assertJsonPath( 'id', '__base__' );
	} );

	it( 'returns the numeric DB id once the user has customized', function (): void {
		$record = GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [ 'color' => [] ],
			'styles'   => [ 'typography' => [ 'fontSize' => '18px' ] ],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/lookup' )
			->assertOk()
			->assertJsonPath( 'id', $record->id );
	} );
} );

describe( 'GET /visual-editor/api/global-styles/base', function (): void {
	it( 'returns the resolver view (theme defaults or DB-merged)', function (): void {
		GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [ 'color' => [ 'palette' => [] ] ],
			'styles'   => [ 'typography' => [ 'fontSize' => '20px' ] ],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/base' )
			->assertOk()
			->assertJsonPath( 'theme', 'digital-shopfront' )
			->assertJsonPath( 'styles.typography.fontSize', '20px' );
	} );
} );

describe( 'GET /visual-editor/api/global-styles/{id}', function (): void {
	it( 'returns the singleton when id matches the resolver state', function (): void {
		$record = GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [],
			'styles'   => [],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( "/visual-editor/api/global-styles/{$record->id}" )
			->assertOk()
			->assertJsonPath( 'id', $record->id );
	} );

	it( 'returns 404 when the id does not match the singleton', function (): void {
		GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [],
			'styles'   => [],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/9999' )->assertNotFound();
	} );
} );

describe( 'PUT /visual-editor/api/global-styles/{id}', function (): void {
	it( 'upserts a new row when id is __base__ and no DB record exists', function (): void {
		$this->putJson( '/visual-editor/api/global-styles/__base__', [
			'theme'    => 'digital-shopfront',
			'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ] ],
			'styles'   => [ 'typography' => [ 'fontSize' => '16px' ] ],
		] )
			->assertOk()
			->assertJsonPath( 'theme', 'digital-shopfront' )
			->assertJsonPath( 'styles.typography.fontSize', '16px' );

		expect( GlobalStyles::query()->where( 'theme', 'digital-shopfront' )->exists() )->toBeTrue();
	} );

	it( 'updates the existing record when id is numeric', function (): void {
		$record = GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Original',
			'settings' => [],
			'styles'   => [ 'typography' => [ 'fontSize' => '14px' ] ],
		] );

		$this->putJson( "/visual-editor/api/global-styles/{$record->id}", [
			'theme'  => 'digital-shopfront',
			'styles' => [ 'typography' => [ 'fontSize' => '18px' ] ],
		] )
			->assertOk()
			->assertJsonPath( 'styles.typography.fontSize', '18px' );
	} );

	it( 'returns 404 when the numeric id does not exist', function (): void {
		$this->putJson( '/visual-editor/api/global-styles/9999', [
			'theme'  => 'digital-shopfront',
			'styles' => [],
		] )->assertNotFound();
	} );
} );
