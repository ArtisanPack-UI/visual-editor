<?php

/**
 * H6 TemplateController standalone-install tests — exercises behavior
 * when cms-framework is autoloaded (because it's a require-dev) but
 * its provider has NOT been booted. This is the moral equivalent of
 * a host app that doesn't `composer require artisanpack-ui/cms-framework`:
 * the class isn't useful, and any DB write would have nowhere to land.
 *
 * The controller's `cmsFrameworkAvailable()` gate combines `class_exists`
 * with a service-binding check on cms-framework's resolver — that
 * binding only exists if the SiteEditor provider booted, so this test
 * (which does NOT use the WithCmsFramework trait) sees the gate evaluate
 * false and asserts the standalone fallback paths.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Standalone tester',
		'email'    => 'standalone+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );
} );

it( 'lists an empty array when no contributors are registered', function (): void {
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/templates' )
		->assertOk()
		->assertExactJson( [] );
} );

it( 'returns 404 on POST when cms-framework is not integrated', function (): void {
	$this->postJson( '/visual-editor/api/templates', [
		'slug'  => 'archive',
		'title' => 'Archive',
		'theme' => 'digital-shopfront',
	] )
		->assertNotFound()
		->assertJsonPath( 'message', 'The site editor requires artisanpack-ui/cms-framework.' );
} );

it( 'returns 404 on PUT when cms-framework is not integrated', function (): void {
	$this->putJson( '/visual-editor/api/templates/single', [
		'theme' => 'digital-shopfront',
		'title' => 'Single',
	] )->assertNotFound();
} );

it( 'returns 404 on DELETE when cms-framework is not integrated', function (): void {
	$this->deleteJson( '/visual-editor/api/templates/single' )->assertNotFound();
} );

it( 'lists an empty array for template parts when no contributors are registered', function (): void {
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/template-parts' )
		->assertOk()
		->assertExactJson( [] );
} );

it( 'returns 404 on POST template-parts when cms-framework is not integrated', function (): void {
	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'header',
		'title' => 'Header',
		'area'  => 'header',
		'theme' => 'digital-shopfront',
	] )->assertNotFound();
} );
