<?php

declare( strict_types=1 );

use Tests\TestUser;

it( 'returns 401 for unauthenticated blocks requests', function () {
	$response = $this->getJson( '/visual-editor/api/blocks' );

	$response->assertUnauthorized();
} );

it( 'returns the registered block types', function () {
	$user = TestUser::create( [
		'name'     => 'Jane',
		'email'    => 'jane@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$response = $this->getJson( '/visual-editor/api/blocks' );

	$response->assertOk()
		->assertJsonStructure( [
			'data' => [
				'*' => ['name', 'title', 'category', 'attributes'],
			],
		] );

	$names = collect( $response->json( 'data' ) )->pluck( 'name' )->all();

	expect( $names )->toContain( 'core/paragraph', 'core/heading' );
} );
