<?php

declare( strict_types=1 );

use Tests\TestUser;

it( 'returns 401 for unauthenticated blocks requests', function () {
	$response = $this->getJson( '/visual-editor/api/blocks' );

	$response->assertUnauthorized();
} );

it( 'returns the registered block types from block.json manifests', function () {
	$user = TestUser::create( [
		'name'     => 'Jane',
		'email'    => 'jane@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$response = $this->getJson( '/visual-editor/api/blocks' );

	$response->assertOk()
		->assertJsonStructure( [
			'blocks' => [
				'*' => ['name', 'title'],
			],
		] );

	$names = collect( $response->json( 'blocks' ) )->pluck( 'name' )->all();

	expect( $names )->toContain( 'artisanpack/paragraph', 'artisanpack/heading' );
} );
