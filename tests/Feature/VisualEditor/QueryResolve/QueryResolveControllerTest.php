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

it( 'returns an empty paginator for relatedTo when the host post cannot be loaded (#601)', function () {
	actingResolver();

	test()->fake->setItems( [] );

	$this->postJson( '/visual-editor/api/query/resolve', [
		'relatedTo' => 999,
		'perPage'   => 3,
	] )
		->assertOk()
		->assertJsonPath( 'meta.total', 0 )
		->assertJsonPath( 'meta.per_page', 3 )
		->assertJsonPath( 'data', [] );
} );

it( 'rejects relatedTo paired with taxQuery (#601)', function () {
	actingResolver();

	$this->postJson( '/visual-editor/api/query/resolve', [
		'relatedTo' => 1,
		'taxQuery'  => [ 'taxonomy' => 'category', 'terms' => [ 1 ] ],
	] )
		->assertStatus( 422 );
} );

it( 'expands relatedTo into a taxonomy query against the host post (#601)', function () {
	actingResolver();

	$related = TestBlockContentModel::create( [
		'title'   => 'Related',
		'status'  => 'published',
		'content' => [],
	] );

	$term      = new \stdClass();
	$term->id  = 7;

	$host             = new \stdClass();
	$host->id         = 5;
	$host->title      = 'Host';
	$host->post_type  = 'post';
	$host->categories = [ $term ];

	// Two consecutive resolve() calls: first loads the host, second
	// runs the expanded related query. Track each invocation so we
	// can assert on the second payload.
	$smartFake = new class( $host, $related ) extends FakeQueryResolver {
		/** @var array<int, array<string, mixed>> */
		public array $callLog = [];

		// phpcs:ignore
		public function __construct( private object $hostPost, private mixed $relatedPost ) {}

		public function resolve( array $attributes ): \Illuminate\Contracts\Pagination\LengthAwarePaginator
		{
			$this->callLog[] = $attributes;

			if ( isset( $attributes['include'] ) ) {
				return new \Illuminate\Pagination\LengthAwarePaginator(
					[ $this->hostPost ],
					1,
					1,
					1
				);
			}

			return new \Illuminate\Pagination\LengthAwarePaginator(
				[ $this->relatedPost ],
				1,
				isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 3,
				1
			);
		}
	};

	$this->app->instance( QueryResolverContract::class, $smartFake );

	$this->postJson( '/visual-editor/api/query/resolve', [
		'relatedTo' => 5,
		'postType'  => 'post',
		'perPage'   => 3,
	] )
		->assertOk()
		->assertJsonPath( 'data.0.id', $related->id );

	expect( $smartFake->callLog )->toHaveCount( 2 );
	expect( $smartFake->callLog[1] )->toHaveKey( 'taxQuery' )
		->and( $smartFake->callLog[1]['taxQuery']['taxonomy'] )->toBe( 'category' )
		->and( $smartFake->callLog[1]['taxQuery']['terms'] )->toBe( [ 7 ] )
		->and( $smartFake->callLog[1]['exclude'] )->toBe( [ 5 ] )
		->and( $smartFake->callLog[1] )->not->toHaveKey( 'relatedTo' );
} );
