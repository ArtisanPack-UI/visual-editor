<?php

declare( strict_types=1 );

use Tests\TestUser;

it( 'returns 401 for unauthenticated visitors', function () {
	$this->getJson( '/visual-editor/api/users/search?q=me' )
		->assertStatus( 401 );
} );

it( 'returns an empty result for an empty search term', function () {
	$user = TestUser::create( [ 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => bcrypt( 'x' ) ] );

	$this->actingAs( $user )
		->getJson( '/visual-editor/api/users/search?q=' )
		->assertOk()
		->assertJson( [ 'data' => [] ] );
} );

it( 'returns matching users by email substring', function () {
	TestUser::create( [ 'name' => 'Ada Lovelace',    'email' => 'ada@example.com',    'password' => bcrypt( 'x' ) ] );
	TestUser::create( [ 'name' => 'Grace Hopper',    'email' => 'grace@example.com',  'password' => bcrypt( 'x' ) ] );
	TestUser::create( [ 'name' => 'Alan Turing',     'email' => 'alan@example.com',   'password' => bcrypt( 'x' ) ] );

	$requester = TestUser::create( [ 'name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt( 'x' ) ] );

	$response = $this->actingAs( $requester )
		->getJson( '/visual-editor/api/users/search?q=ada' )
		->assertOk()
		->json( 'data' );

	expect( $response )->toBeArray()->and( count( $response ) )->toBeGreaterThan( 0 );
	expect( collect( $response )->pluck( 'email' )->all() )->toContain( 'ada@example.com' );
} );

it( 'respects the limit query parameter', function () {
	for ( $i = 1; $i <= 5; $i++ ) {
		TestUser::create( [ 'name' => "User $i", 'email' => "user$i@example.com", 'password' => bcrypt( 'x' ) ] );
	}

	$requester = TestUser::create( [ 'name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt( 'x' ) ] );

	$response = $this->actingAs( $requester )
		->getJson( '/visual-editor/api/users/search?q=user&limit=2' )
		->assertOk()
		->json( 'data' );

	expect( $response )->toHaveCount( 2 );
} );
