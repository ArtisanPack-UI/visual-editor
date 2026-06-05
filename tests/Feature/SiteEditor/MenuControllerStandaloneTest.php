<?php

/**
 * H6 Menu + MenuItem controllers standalone-install tests.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Standalone tester',
		'email'    => 'standalone-menus+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );
} );

it( 'lists an empty array for menus when cms-framework is not integrated', function (): void {
	$this->getJson( '/visual-editor/api/menus' )
		->assertOk()
		->assertExactJson( [] );
} );

it( 'returns 404 on POST menus when cms-framework is not integrated', function (): void {
	$this->postJson( '/visual-editor/api/menus', [
		'theme' => 'digital-shopfront',
		'slug'  => 'primary',
		'name'  => 'Primary',
	] )
		->assertNotFound()
		->assertJsonPath( 'message', 'The site editor requires artisanpack-ui/cms-framework.' );
} );

it( 'returns 404 on PUT menus when cms-framework is not integrated', function (): void {
	$this->putJson( '/visual-editor/api/menus/1', [ 'name' => 'Renamed' ] )->assertNotFound();
} );

it( 'returns 404 on DELETE menus when cms-framework is not integrated', function (): void {
	$this->deleteJson( '/visual-editor/api/menus/1' )->assertNotFound();
} );

it( 'returns an empty array on GET /menu-items in standalone mode (short-circuit precedes the menu_id validation)', function (): void {
	// In integrated mode, omitting menu_id returns 422. In standalone, the
	// `cmsFrameworkAvailable()` gate short-circuits to an empty list before
	// the validation fires, so callers see a deterministic empty response
	// regardless of whether they passed menu_id.
	$this->getJson( '/visual-editor/api/menu-items' )
		->assertOk()
		->assertExactJson( [] );
} );

it( 'returns 404 on POST menu-items when cms-framework is not integrated', function (): void {
	$this->postJson( '/visual-editor/api/menu-items', [
		'menu_id' => 1,
		'label'   => 'Home',
	] )->assertNotFound();
} );

it( 'returns 404 on DELETE menu-items when cms-framework is not integrated', function (): void {
	$this->deleteJson( '/visual-editor/api/menu-items/1' )->assertNotFound();
} );
