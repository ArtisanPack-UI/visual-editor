<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use Tests\TestUser;

function createPost( array $overrides = [] ): VisualEditorPost
{
	$authorId = $overrides['author_id'] ?? null;
	unset( $overrides['author_id'] );

	$post = VisualEditorPost::create( array_merge( [
		'title'  => 'Test Post',
		'blocks' => [
			[
				'clientId'    => 'abc-123',
				'name'        => 'artisanpack/paragraph',
				'attributes'  => ['content' => 'Hello'],
				'innerBlocks' => [],
			],
		],
	], $overrides ) );

	if ( null !== $authorId ) {
		$post->author_id = $authorId;
		$post->save();
	}

	return $post;
}

function actingAsUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Jane Doe',
		'email'    => 'jane+' . uniqid() . '@example.com',
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

it( 'returns 403 on PUT when ownership restriction blocks the user', function () {
	config()->set( 'artisanpack.visual-editor.authorization.restrict_by_owner', true );

	$owner = TestUser::create( [
		'name'     => 'Owner',
		'email'    => 'owner@example.com',
		'password' => bcrypt( 'secret' ),
	] );
	$post = createPost( ['author_id' => $owner->id] );

	actingAsUser();

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => [
			[
				'clientId'    => 'c-1',
				'name'        => 'artisanpack/paragraph',
				'attributes'  => ['content' => 'Hello'],
				'innerBlocks' => [],
			],
		],
	] );

	$response->assertForbidden();

	expect( $post->fresh()->blocks )->toEqual( [
		[
			'clientId'    => 'abc-123',
			'name'        => 'artisanpack/paragraph',
			'attributes'  => ['content' => 'Hello'],
			'innerBlocks' => [],
		],
	] );
} );

it( 'returns the block tree for an authorized GET request', function () {
	actingAsUser();
	$post = createPost();

	$response = $this->getJson( "/visual-editor/api/posts/{$post->id}" );

	$response->assertOk()
		->assertJsonPath( 'id', $post->id )
		->assertJsonPath( 'title', 'Test Post' )
		->assertJsonPath( 'blocks.0.clientId', 'abc-123' )
		->assertJsonPath( 'blocks.0.name', 'artisanpack/paragraph' )
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

it( 'rejects duplicate clientIds within the tree', function () {
	actingAsUser();
	$post = createPost();

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => [
			[
				'clientId'    => 'dupe',
				'name'        => 'artisanpack/paragraph',
				'attributes'  => ['content' => 'a'],
				'innerBlocks' => [],
			],
			[
				'clientId'    => 'dupe',
				'name'        => 'artisanpack/paragraph',
				'attributes'  => ['content' => 'b'],
				'innerBlocks' => [],
			],
		],
	] );

	$response->assertUnprocessable()
		->assertJsonValidationErrors( 'blocks' );
} );

it( 'rejects block trees deeper than MAX_DEPTH', function () {
	actingAsUser();
	$post = createPost();

	$build = function ( int $depth ) use ( &$build ): array {
		$block = [
			'clientId'    => "c-{$depth}",
			'name'        => 'artisanpack/paragraph',
			'attributes'  => [],
			'innerBlocks' => [],
		];

		if ( $depth > 0 ) {
			$block['innerBlocks'] = [$build( $depth - 1 )];
		}

		return $block;
	};

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => [$build( \ArtisanPackUI\VisualEditor\Rules\BlockTreeRule::MAX_DEPTH )],
	] );

	$response->assertUnprocessable()
		->assertJsonValidationErrors( 'blocks' );
} );

it( 'rejects block trees with more than MAX_NODES blocks', function () {
	actingAsUser();
	$post = createPost();

	$count  = \ArtisanPackUI\VisualEditor\Rules\BlockTreeRule::MAX_NODES + 1;
	$blocks = [];
	for ( $i = 0; $i < $count; $i++ ) {
		$blocks[] = [
			'clientId'    => "c-{$i}",
			'name'        => 'artisanpack/paragraph',
			'attributes'  => [],
			'innerBlocks' => [],
		];
	}

	$response = $this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'blocks' => $blocks,
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
			'name'        => 'artisanpack/heading',
			'attributes'  => ['content' => 'Updated', 'level' => 2],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'paragraph-1',
			'name'        => 'artisanpack/paragraph',
			'attributes'  => ['content' => 'Body text'],
			'innerBlocks' => [
				[
					'clientId'    => 'inner-1',
					'name'        => 'artisanpack/paragraph',
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
