<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\BlockCssAccess;
use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPageModel;
use Tests\Fixtures\TestBlockContentPagePolicy;
use Tests\Fixtures\TestBlockContentPolicy;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentModel::class,
		'pages' => TestBlockContentPageModel::class,
	] );

	// Service provider boot already ran with empty resources config.
	// Re-bind ResourceResolver so this test's config takes effect.
	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

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

it( 'returns 401 for unauthenticated GET', function (): void {
	$model = TestBlockContentModel::create( [
		'title'   => 'Unauthed',
		'status'  => 'published',
		'content' => validBlockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$model->id}/content" )
		->assertUnauthorized();
} );

it( 'returns 404 for an unregistered resource slug', function (): void {
	makeActor();

	$this->getJson( '/visual-editor/api/orders/1/content' )->assertNotFound();
} );

it( 'returns 404 when the record does not exist', function (): void {
	makeActor();

	$this->getJson( '/visual-editor/api/posts/999/content' )->assertNotFound();
} );

it( 'returns the block tree for an authorized GET', function (): void {
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

it( 'applies the configured query scope when resolving resources', function (): void {
	makeActor();

	$draft = TestBlockContentModel::create( [
		'title'   => 'Draft',
		'status'  => 'draft',
		'content' => validBlockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$draft->id}/content" )
		->assertNotFound();
} );

it( 'honors the custom block content column on PUT', function (): void {
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

it( 'forbids PUT when the policy denies update', function (): void {
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

it( 'validates the block tree shape on PUT', function (): void {
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

it( 'persists a valid block tree on PUT', function (): void {
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

it( 'surfaces a HasBlockContent validation error on first resolve', function (): void {
	config()->set( 'artisanpack.visual-editor.resources.broken', TestUser::class );

	// Re-bind so the new config entry lands in the resolver.
	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	makeActor();

	$this->withoutExceptionHandling();

	expect( fn () => $this->getJson( '/visual-editor/api/broken/1/content' ) )
		->toThrow( InvalidArgumentException::class );
} );

function customCssBlock( string $clientId, string $css ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => 'Styled', 'artisanpackEffects' => [ 'opacity' => 80, 'customCss' => $css ] ],
		'innerBlocks' => [],
	];
}

it( 'strips new Custom CSS on PUT for users without the block-CSS ability (#881)', function (): void {
	makeActor();

	// Seeded as a trusted write: the model-level guard (H-2) would
	// otherwise strip it, since the test acts as a user without the ability.
	$model = app( CustomCssModelGuard::class )->trusted( static fn (): TestBlockContentModel => TestBlockContentModel::create( [
		'title'   => 'Css',
		'status'  => 'published',
		'content' => [ customCssBlock( 'kept', 'color: red;' ) ],
	] ) );

	$this->putJson( "/visual-editor/api/posts/{$model->id}/content", [
		'blocks' => [ customCssBlock( 'kept', 'color: red;' ), customCssBlock( 'new', 'display: none;' ) ],
	] )->assertOk();

	$stored = $model->fresh()->getBlockContent();

	expect( $stored[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 80, 'customCss' => 'color: red;' ] )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 80 ] );
} );

it( 'saves Custom CSS on PUT for users with the block-CSS ability (#881)', function (): void {
	makeActor();

	Gate::define( BlockCssAccess::ABILITY, fn ( $user ): bool => true );

	$model = TestBlockContentModel::create( [ 'title' => 'Css', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$model->id}/content", [
		'blocks' => [ customCssBlock( 'new', 'display: none;' ) ],
	] )->assertOk();

	expect( $model->fresh()->getBlockContent()[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'display: none;' );
} );
