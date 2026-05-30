<?php

/**
 * Feature tests for the singleton site-meta endpoint (#481).
 *
 * The endpoint serves the editor's `artisanpack/site-*` block previews
 * through the core-data shim's `useEntityRecord('root', '__unstableBase',
 * 'self')`. Tests cover the happy-path shape, the empty-config
 * fallback, and the middleware-driven auth gate.
 */

declare( strict_types=1 );

use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$this->actor = TestUser::create( [
		'name'     => 'Site Tester',
		'email'    => 'site+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'returns the configured site-meta envelope', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title'       => 'ArtisanPack Studios',
		'description' => 'Crafting beautiful interfaces.',
		'url'         => 'https://artisanpack.test',
		'logo_id'     => null,
		'icon_id'     => null,
	] );

	$this->getJson( '/visual-editor/api/site/self' )
		->assertOk()
		->assertJsonPath( 'id', 'self' )
		->assertJsonPath( 'title.raw', 'ArtisanPack Studios' )
		->assertJsonPath( 'title.rendered', 'ArtisanPack Studios' )
		->assertJsonPath( 'description.raw', 'Crafting beautiful interfaces.' )
		->assertJsonPath( 'description.rendered', 'Crafting beautiful interfaces.' )
		->assertJsonPath( 'url', 'https://artisanpack.test' )
		->assertJsonPath( 'logo', null )
		->assertJsonPath( 'icon', null )
		->assertJsonPath( 'logoUrl', '' );
} );

it( 'returns empty strings when no source has populated the meta', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title'       => null,
		'description' => null,
		'url'         => null,
		'logo_id'     => null,
		'icon_id'     => null,
	] );

	$this->getJson( '/visual-editor/api/site/self' )
		->assertOk()
		->assertJsonPath( 'title.raw', '' )
		->assertJsonPath( 'description.raw', '' )
		->assertJsonPath( 'url', '' )
		->assertJsonPath( 'logo', null )
		->assertJsonPath( 'logoUrl', '' );
} );

it( 'ignores the id segment and always returns the singleton record', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title'       => 'Sentinel Test',
		'description' => null,
		'url'         => null,
		'logo_id'     => null,
		'icon_id'     => null,
	] );

	$this->getJson( '/visual-editor/api/site/anything-goes' )
		->assertOk()
		->assertJsonPath( 'id', 'anything-goes' )
		->assertJsonPath( 'title.raw', 'Sentinel Test' );
} );

it( 'rejects unauthenticated requests when the api middleware requires auth', function () {
	auth()->logout();

	$this->getJson( '/visual-editor/api/site/self' )->assertUnauthorized();
} );
