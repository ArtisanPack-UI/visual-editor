<?php

/**
 * H6 PatternController + GlobalStylesController standalone-install
 * tests — exercises behavior when cms-framework is autoloaded but its
 * provider has NOT been booted (the moral equivalent of a host app
 * without `composer require artisanpack-ui/cms-framework`).
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
		'email'    => 'standalone-patterns+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );
} );

it( 'lists an empty array for patterns when no contributors are registered', function (): void {
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/patterns' )
		->assertOk()
		->assertExactJson( [] );
} );

it( 'returns 404 on POST patterns when cms-framework is not integrated', function (): void {
	$this->postJson( '/visual-editor/api/patterns', [
		'slug'  => 'cta',
		'title' => 'CTA',
	] )
		->assertNotFound()
		->assertJsonPath( 'message', 'The site editor requires artisanpack-ui/cms-framework.' );
} );

it( 'returns 404 on PUT patterns when cms-framework is not integrated', function (): void {
	$this->putJson( '/visual-editor/api/patterns/user/cta', [
		'title' => 'Renamed',
	] )->assertNotFound();
} );

it( 'returns 404 on DELETE patterns when cms-framework is not integrated', function (): void {
	$this->deleteJson( '/visual-editor/api/patterns/user/cta' )->assertNotFound();
} );

it( 'returns __base__ as the global-styles lookup id when no resolver entry exists', function (): void {
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->getJson( '/visual-editor/api/global-styles/lookup' )
		->assertOk()
		->assertJsonPath( 'id', '__base__' );
} );

it( 'returns 404 on PUT global-styles when cms-framework is not integrated', function (): void {
	$this->putJson( '/visual-editor/api/global-styles/__base__', [
		'theme'  => 'digital-shopfront',
		'styles' => [],
	] )->assertNotFound();
} );
