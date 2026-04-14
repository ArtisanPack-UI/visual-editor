<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use Tests\TestUser;

function createPost( array $overrides = [] ): VisualEditorPost
{
	return VisualEditorPost::create( array_merge( [
		'title'  => 'Test Post',
		'blocks' => [
			[
				'clientId'    => 'abc-123',
				'name'        => 'core/paragraph',
				'attributes'  => ['content' => 'Hello'],
				'innerBlocks' => [],
			],
		],
	], $overrides ) );
}

function actingAsUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Jane Doe',
		'email'    => 'jane@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 for unauthenticated GET requests', function () {
	$post = createPost();

	$response = $this->getJson( "/visual-editor/api/posts/{$post->id}" );

	$response->assertUnauthorized();
} );

it( 'returns 403 when ownership restriction blocks the user', function () {
	config()->set( 'artisanpack.visual-editor.authorization.restrict_by_owner', true );

	$owner = TestUser::create( [
		'name'     => 'Owner',
		'email'    => 'owner@example.com',
		'password' => bcrypt( 'secret' ),
	] );
	$post = createPost( ['author_id' => $owner->id] );

	actingAsUser();

	$response = $this->getJson( "/visual-editor/api/posts/{$post->id}" );

	$response->assertForbidden();
} );

it( 'returns the block tree for an authorized GET request', function () {
	actingAsUser();
	$post = createPost();

	$response = $this->getJson( "/visual-editor/api/posts/{$post->id}" );

	$response->assertOk()
		->assertJsonPath( 'id', $post->id )
		->assertJsonPath( 'title', 'Test Post' )
		->assertJsonPath( 'blocks.0.clientId', 'abc-123' )
		->assertJsonPath( 'blocks.0.name', 'core/paragraph' )
		->assertJsonPath( 'blocks.0.attributes.content', 'Hello' );
} );

it( 'validates the block tree shape on PUT', function () {
	actingAsUser();
	$post = createPost();

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => [
			['clientId' => 'only-id'],
		],
	] );

	$response->assertUnprocessable()
		->assertJsonValidationErrors( 'blocks' );
} );

it( 'requires the blocks key on PUT', function () {
	actingAsUser();
	$post = createPost();

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [] );

	$response->assertUnprocessable()
		->assertJsonValidationErrors( 'blocks' );
} );

it( 'persists a valid block tree on PUT', function () {
	actingAsUser();
	$post = createPost();

	$newBlocks = [
		[
			'clientId'    => 'heading-1',
			'name'        => 'core/heading',
			'attributes'  => ['content' => 'Updated', 'level' => 2],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'paragraph-1',
			'name'        => 'core/paragraph',
			'attributes'  => ['content' => 'Body text'],
			'innerBlocks' => [
				[
					'clientId'    => 'inner-1',
					'name'        => 'core/paragraph',
					'attributes'  => ['content' => 'Nested'],
					'innerBlocks' => [],
				],
			],
		],
	];

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => $newBlocks,
	] );

	$response->assertOk()
		->assertJsonPath( 'blocks.0.clientId', 'heading-1' )
		->assertJsonPath( 'blocks.1.innerBlocks.0.clientId', 'inner-1' );

	expect( $post->fresh()->blocks )->toEqual( $newBlocks );
} );

it( 'returns 401 for unauthenticated PUT requests', function () {
	$post = createPost();

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => [],
	] );

	$response->assertUnauthorized();
} );
