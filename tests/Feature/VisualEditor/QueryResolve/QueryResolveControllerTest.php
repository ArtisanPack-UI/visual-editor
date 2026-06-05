<?php

declare( strict_types=1 );

/**
 * Feature tests for `POST /visual-editor/api/query/resolve`. Uses a
 * fake {@see QueryResolverContract} bound at runtime so the suite does
 * not require cms-framework on the autoloader.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestG3Policy;
use Tests\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
	test()->fake = new FakeQueryResolver();

	$this->app->instance( QueryResolverContract::class, test()->fake );

	Gate::policy( TestBlockContentModel::class, TestG3Policy::class );
} );

function actingResolver(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Tester',
		'email'    => 'tester+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'rejects unauthenticated requests', function () {
	$this->postJson( '/visual-editor/api/query/resolve', [ 'postType' => 'post' ] )
		->assertUnauthorized();
} );

it( 'returns 503 when no resolver is bound', function () {
	$this->app->forgetInstance( QueryResolverContract::class );
	$this->app->offsetUnset( QueryResolverContract::class );

	actingResolver();

	$this->postJson( '/visual-editor/api/query/resolve', [ 'postType' => 'post' ] )
		->assertStatus( 503 )
		->assertJsonPath( 'message', 'Query runtime is not available. Install artisanpack-ui/cms-framework or bind a custom resolver to QueryResolverContract.' );
} );

it( 'returns paginated WP-shape results when the resolver succeeds', function () {
	actingResolver();

	$post = TestBlockContentModel::create( [
		'title'   => 'Hello',
		'status'  => 'published',
		'content' => [],
	] );

	test()->fake->setItems( [ $post ] );
	test()->fake->totalOverride = 47;
	test()->fake->perPage       = 10;
	test()->fake->currentPage   = 1;

	$this->postJson( '/visual-editor/api/query/resolve', [
		'postType' => 'post',
		'perPage'  => 10,
	] )
		->assertOk()
		->assertJsonPath( 'data.0.id', $post->id )
		->assertJsonPath( 'data.0.title.rendered', 'Hello' )
		->assertJsonPath( 'meta.total', 47 )
		->assertJsonPath( 'meta.per_page', 10 )
		->assertJsonPath( 'meta.current_page', 1 )
		->assertJsonPath( 'meta.last_page', 5 );
} );

it( 'forwards the validated payload to the resolver', function () {
	actingResolver();

	$this->postJson( '/visual-editor/api/query/resolve', [
		'postType' => 'post',
		'perPage'  => 5,
		'orderBy'  => 'title',
		'order'    => 'asc',
		'taxQuery' => [ 'taxonomy' => 'category', 'terms' => [ 3, 4 ], 'operator' => 'IN' ],
	] )->assertOk();

	expect( test()->fake->lastAttributes )->toMatchArray( [
		'postType' => 'post',
		'perPage'  => 5,
		'orderBy'  => 'title',
		'order'    => 'asc',
	] );
	expect( test()->fake->lastAttributes['taxQuery']['terms'] )->toBe( [ 3, 4 ] );
} );

it( 'rejects an invalid orderBy value', function () {
	actingResolver();

	$this->postJson( '/visual-editor/api/query/resolve', [
		'postType' => 'post',
		'orderBy'  => 'whatever',
	] )->assertUnprocessable();
} );

it( 'rejects perPage above the documented cap', function () {
	actingResolver();

	$this->postJson( '/visual-editor/api/query/resolve', [
		'postType' => 'post',
		'perPage'  => 9999,
	] )->assertUnprocessable();
} );

it( 'includes the editor-preview envelope on resolved posts (#483)', function () {
	actingResolver();

	$post = TestBlockContentModel::create( [
		'title'   => 'Hello',
		'status'  => 'published',
		'content' => [],
	] );

	test()->fake->setItems( [ $post ] );

	$response = $this->postJson( '/visual-editor/api/query/resolve', [
		'postType' => 'post',
		'perPage'  => 1,
	] )->assertOk();

	// The envelope must always be present — the editor canvas reads
	// it through `mapWpEntityToPost` — even when the underlying
	// model exposes no author / featured-media data (the fixture
	// model has neither), in which case both fields are null.
	$response->assertJsonStructure( [
		'data' => [
			[ '_preview' => [ 'dateFormatted', 'author', 'featuredImage' ] ],
		],
	] );

	$response->assertJsonPath( 'data.0._preview.author', null );
	$response->assertJsonPath( 'data.0._preview.featuredImage', null );
} );

it( 'returns 400 when the resolver throws', function () {
	actingResolver();

	$throwingFake = new class extends FakeQueryResolver {
		public function resolve( array $attributes ): \Illuminate\Contracts\Pagination\LengthAwarePaginator
		{
			throw new \InvalidArgumentException( 'unknown post type' );
		}
	};

	$this->app->instance( QueryResolverContract::class, $throwingFake );

	$this->postJson( '/visual-editor/api/query/resolve', [
		'postType' => 'something-unregistered',
	] )
		->assertStatus( 400 )
		->assertJsonPath( 'message', 'Failed to resolve the query payload.' );
} );
