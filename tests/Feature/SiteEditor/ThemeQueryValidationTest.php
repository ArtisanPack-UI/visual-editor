<?php

/**
 * Non-string query values on the site-editor endpoints (#1075, #1092).
 *
 * `?theme[]=x` (or `?status[]=x`, `?area[]=x`, …) arrives as an array.
 * The endpoints answer with a 422 in the existing validation shape
 * instead of a 500 from a string cast.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->actingAs( TestUser::create( [
		'name'     => 'Theme query tester',
		'email'    => 'theme-query+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

it( 'returns 422 for an array theme on the read endpoints', function ( string $path ): void {
	$this->getJson( $path . '?theme[]=x' )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'theme' );
} )->with( [
	'template parts index' => '/visual-editor/api/template-parts',
	'menus index'          => '/visual-editor/api/menus',
] );

it( 'returns 422 for an array theme on the delete endpoints and keeps the row', function ( string $model, string $route, array $attributes ): void {
	$row = $model::create( array_merge( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'single',
		'title'         => 'Single',
		'status'        => 'publish',
		'is_custom'     => false,
		'block_content' => [],
		'author_id'     => null,
	], $attributes ) );

	$this->deleteJson( "/visual-editor/api/{$route}/single?theme[]=x" )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'theme' );

	$this->deleteJson( "/visual-editor/api/{$route}/{$row->id}?theme[]=x" )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'theme' );

	expect( $model::query()->whereKey( $row->id )->exists() )->toBeTrue();
} )->with( [
	'templates'      => [ Template::class, 'templates', [] ],
	'template parts' => [ TemplatePart::class, 'template-parts', [ 'area' => 'general' ] ],
] );

it( 'still filters by a string theme', function (): void {
	TemplatePart::create( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'header',
		'title'         => 'Header',
		'area'          => 'header',
		'status'        => 'publish',
		'is_custom'     => false,
		'block_content' => [],
		'author_id'     => null,
	] );

	( new ArtisanPackUI\VisualEditor\VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/template-parts?theme=digital-shopfront' )
		->assertOk()
		->assertJsonPath( '0.slug', 'header' );

	$this->getJson( '/visual-editor/api/template-parts?theme=other-theme' )
		->assertOk()
		->assertExactJson( [] );
} );

it( 'returns 422 for an array value on the list filters (#1092)', function ( string $path, string $key ): void {
	( new ArtisanPackUI\VisualEditor\VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( "{$path}?{$key}[]=x" )
		->assertUnprocessable()
		->assertJsonValidationErrors( $key )
		->assertJsonPath( "errors.{$key}.0", "The {$key} query parameter must be a string." );
} )->with( [
	'templates status'     => [ '/visual-editor/api/templates', 'status' ],
	'templates theme'      => [ '/visual-editor/api/templates', 'theme' ],
	'templates slug'       => [ '/visual-editor/api/templates', 'slug' ],
	'template parts area'  => [ '/visual-editor/api/template-parts', 'area' ],
	'template parts slug'  => [ '/visual-editor/api/template-parts', 'slug' ],
	'patterns source'      => [ '/visual-editor/api/patterns', 'source' ],
	'patterns post type'   => [ '/visual-editor/api/patterns', 'post_type' ],
] );

it( 'still filters by string list values (#1092)', function (): void {
	TemplatePart::create( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'header',
		'title'         => 'Header',
		'area'          => 'header',
		'status'        => 'publish',
		'is_custom'     => false,
		'block_content' => [],
		'author_id'     => null,
	] );

	Template::create( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'single',
		'title'         => 'Single',
		'status'        => 'draft',
		'is_custom'     => false,
		'block_content' => [],
		'author_id'     => null,
	] );

	( new ArtisanPackUI\VisualEditor\VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/template-parts?area=header&slug=header' )
		->assertOk()
		->assertJsonPath( '0.slug', 'header' );

	$this->getJson( '/visual-editor/api/template-parts?area=footer' )
		->assertOk()
		->assertExactJson( [] );

	$this->getJson( '/visual-editor/api/templates?status=draft' )
		->assertOk()
		->assertJsonPath( '0.slug', 'single' );

	$this->getJson( '/visual-editor/api/templates?status=publish&slug=single' )
		->assertOk()
		->assertExactJson( [] );

	$this->getJson( '/visual-editor/api/patterns?source=theme&post_type=page' )->assertOk();
} );
