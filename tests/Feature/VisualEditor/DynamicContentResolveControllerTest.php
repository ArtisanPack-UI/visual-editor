<?php

declare( strict_types=1 );

use Tests\Support\FakeDynamicContentAccessor;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$this->actor = TestUser::create( [
		'name'     => 'DC Resolve Tester',
		'email'    => 'dcr+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );

	$fake = new FakeDynamicContentAccessor( [
		'business_info' => [
			'phone' => '(555) 123-4567',
			'email' => 'hi@example.com',
		],
		'team' => [
			[ 'name' => 'Alice' ],
			[ 'name' => 'Bob' ],
		],
	] );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		$fake
	);

	app()->bind(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		fn () => $fake
	);
} );

it( 'resolves a batch of tokens', function () {
	$response = $this->postJson( '/visual-editor/api/dynamic-content/resolve', [
		'tokens' => [
			'business_info.phone',
			'business_info.email',
			'team[0].name',
			'unknown.field',
		],
	] );

	$response->assertOk();

	$values = $response->json( 'values' );

	expect( $values['business_info.phone'] )->toBe( '(555) 123-4567' );
	expect( $values['business_info.email'] )->toBe( 'hi@example.com' );
	expect( $values['team[0].name'] )->toBe( 'Alice' );
	expect( $values['unknown.field'] )->toBeNull();
} );

it( 'rejects a non-array tokens payload', function () {
	$response = $this->postJson( '/visual-editor/api/dynamic-content/resolve', [
		'tokens' => 'not-an-array',
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );
} );

it( 'rejects a payload above the token cap', function () {
	$response = $this->postJson( '/visual-editor/api/dynamic-content/resolve', [
		'tokens' => array_fill( 0, 201, 'business_info.phone' ),
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'error', 'too_many_tokens' );
} );

it( 'returns an empty values object for an empty token list', function () {
	$response = $this->postJson( '/visual-editor/api/dynamic-content/resolve', [
		'tokens' => [],
	] );

	$response->assertOk()->assertJsonPath( 'values', [] );
} );
