<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPageModel;
use Tests\Fixtures\TestBlockContentPagePolicy;
use Tests\Fixtures\TestBlockContentPolicy;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentModel::class,
		'pages' => TestBlockContentPageModel::class,
	] );

	Gate::policy( TestBlockContentModel::class, TestBlockContentPolicy::class );
	Gate::policy( TestBlockContentPageModel::class, TestBlockContentPagePolicy::class );
} );

function makeActor(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Tester',
		'email'    => 'tester+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function validBlockTree(): array
{
	return [
		[
			'clientId'    => 'abc',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Hello world' ],
			'innerBlocks' => [],
		],
	];
}

it( 'returns 401 for unauthenticated GET', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Unauthed',
		'status'  => 'published',
		'content' => validBlockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$model->id}/content" )
		->assertUnauthorized();
} );

it( 'returns 404 for an unregistered resource slug', function () {
	makeActor();

	$this->getJson( '/visual-editor/api/orders/1/content' )->assertNotFound();
} );

it( 'returns 404 when the record does not exist', function () {
	makeActor();

	$this->getJson( '/visual-editor/api/posts/999/content' )->assertNotFound();
} );

it( 'returns the block tree for an authorized GET', function () {
	makeActor();

	$model = TestBlockContentModel::create( [
		'title'   => 'Hello',
		'status'  => 'published',
		'content' => validBlockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$model->id}/content" )
		->assertOk()
		->assertJsonPath( 'id', $model->id )
		->assertJsonPath( 'resource', 'posts' )
		->assertJsonPath( 'blocks.0.clientId', 'abc' )
		->assertJsonPath( 'blocks.0.name', 'core/paragraph' );
} );

it( 'applies the configured query scope when resolving resources', function () {
	makeActor();

	$draft = TestBlockContentModel::create( [
		'title'   => 'Draft',
		'status'  => 'draft',
		'content' => validBlockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$draft->id}/content" )
		->assertNotFound();
} );

it( 'honors the custom block content column on PUT', function () {
	$user = makeActor();

	$page = TestBlockContentPageModel::create( [
		'title'     => 'Page',
		'author_id' => $user->id,
		'body'      => [],
	] );

	$blocks = validBlockTree();

	$this->putJson( "/visual-editor/api/pages/{$page->id}/content", [ 'blocks' => $blocks ] )
		->assertOk()
		->assertJsonPath( 'resource', 'pages' )
		->assertJsonPath( 'blocks.0.clientId', 'abc' );

	expect( $page->fresh()->body )->toEqual( $blocks );
} );

it( 'forbids PUT when the policy denies update', function () {
	$owner = TestUser::create( [
		'name'     => 'Owner',
		'email'    => 'owner@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$page = TestBlockContentPageModel::create( [
		'title'     => 'Restricted',
		'author_id' => $owner->id,
		'body'      => [],
	] );

	makeActor();

	$this->putJson( "/visual-editor/api/pages/{$page->id}/content", [ 'blocks' => validBlockTree() ] )
		->assertForbidden();

	expect( $page->fresh()->body )->toEqual( [] );
} );

it( 'validates the block tree shape on PUT', function () {
	makeActor();

	$model = TestBlockContentModel::create( [
		'title'   => 'Validate',
		'status'  => 'published',
		'content' => [],
	] );

	$this->putJson( "/visual-editor/api/posts/{$model->id}/content", [
		'blocks' => [ [ 'clientId' => 'only-id' ] ],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'blocks' );
} );

it( 'persists a valid block tree on PUT', function () {
	makeActor();

	$model = TestBlockContentModel::create( [
		'title'   => 'Persist',
		'status'  => 'published',
		'content' => [],
	] );

	$next = [
		[
			'clientId'    => 'h1',
			'name'        => 'core/heading',
			'attributes'  => [ 'content' => 'Updated', 'level' => 2 ],
			'innerBlocks' => [
				[
					'clientId'    => 'p1',
					'name'        => 'core/paragraph',
					'attributes'  => [ 'content' => 'Nested' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$this->putJson( "/visual-editor/api/posts/{$model->id}/content", [ 'blocks' => $next ] )
		->assertOk()
		->assertJsonPath( 'blocks.0.clientId', 'h1' )
		->assertJsonPath( 'blocks.0.innerBlocks.0.clientId', 'p1' );

	expect( $model->fresh()->content )->toEqual( $next );
} );

it( 'returns 500 when a configured resource does not use HasBlockContent', function () {
	config()->set( 'artisanpack.visual-editor.resources.broken', TestUser::class );

	makeActor();

	$this->withoutExceptionHandling();

	expect( fn () => $this->getJson( '/visual-editor/api/broken/1/content' ) )
		->toThrow( RuntimeException::class );
} );

