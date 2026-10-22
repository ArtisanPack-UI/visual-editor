<?php

declare( strict_types=1 );

/**
 * Feature tests for the generic `{resource}` / `{resource}/{id}` record
 * endpoints (#1003). The `packages` slug is served by a resolver
 * subclass that binds a shared, table-less model to its table inside
 * `newModel()` — the same seam a host uses for dynamic content types.
 *
 * @since 1.16.0
 */

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestG3Policy;
use Tests\Fixtures\TestGenericContentModel;
use Tests\Fixtures\TestPublishedOnlyPolicy;
use Tests\TestUser;

beforeEach( function (): void {
	app()->instance( ResourceResolver::class, new class( [ 'packages' => TestGenericContentModel::class, 'posts' => TestBlockContentModel::class ] ) extends ResourceResolver {
		protected function newModel( string $resource, string $modelClass ): Model
		{
			$model = parent::newModel( $resource, $modelClass );

			if ( $model instanceof TestGenericContentModel ) {
				$model->setTable( 'test_block_content_models' );
			}

			return $model;
		}
	} );

	Gate::policy( TestGenericContentModel::class, TestG3Policy::class );
	Gate::policy( TestBlockContentModel::class, TestG3Policy::class );
} );

function recordActor(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Tester',
		'email'    => 'tester+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function makePackage( string $title, string $status = 'published', ?string $slug = null ): TestBlockContentModel
{
	return TestBlockContentModel::create( [
		'title'   => $title,
		'slug'    => $slug,
		'status'  => $status,
		'content' => [],
	] );
}

it( 'returns 401 for unauthenticated list and show requests', function (): void {
	$record = makePackage( 'Hooks' );

	$this->getJson( '/visual-editor/api/packages' )->assertUnauthorized();
	$this->getJson( "/visual-editor/api/packages/{$record->id}" )->assertUnauthorized();
} );

it( 'lists records for a dynamic resource in the minimal picker shape', function (): void {
	recordActor();

	$first  = makePackage( 'Hooks', slug: 'hooks' );
	$second = makePackage( 'Icons' );

	$this->getJson( '/visual-editor/api/packages' )
		->assertOk()
		->assertExactJson( [
			'data' => [
				[ 'id' => $second->id, 'title' => 'Icons', 'slug' => null ],
				[ 'id' => $first->id, 'title' => 'Hooks', 'slug' => 'hooks' ],
			],
		] );
} );

it( 'filters the list by search against the title column', function (): void {
	recordActor();

	makePackage( 'Hooks' );
	$match = makePackage( 'Media Library' );

	$this->getJson( '/visual-editor/api/packages?search=media' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.id', $match->id );
} );

it( 'caps the list at per_page', function (): void {
	recordActor();

	foreach ( range( 1, 4 ) as $i ) {
		makePackage( "Package {$i}" );
	}

	$this->getJson( '/visual-editor/api/packages?per_page=2' )
		->assertOk()
		->assertJsonCount( 2, 'data' );
} );

it( 'limits the list to the include ids, ignoring malformed entries', function (): void {
	recordActor();

	$a = makePackage( 'A' );
	makePackage( 'B' );
	$c = makePackage( 'C' );

	$response = $this->getJson( "/visual-editor/api/packages?include={$a->id},{$c->id},../etc,{$a->id}" )
		->assertOk()
		->assertJsonCount( 2, 'data' );

	expect( array_column( $response->json( 'data' ), 'id' ) )->toEqualCanonicalizing( [ $a->id, $c->id ] );
} );

it( 'returns no records when include has no valid ids', function ( string $include ): void {
	recordActor();

	makePackage( 'A' );
	makePackage( 'B' );

	$this->getJson( "/visual-editor/api/packages?include={$include}" )
		->assertOk()
		->assertExactJson( [ 'data' => [] ] );
} )->with( [
	'path traversal' => [ '../etc' ],
	'non-numeric'    => [ 'abc' ],
	'empty'          => [ '' ],
] );

it( 'drops non-numeric include ids for an integer key', function (): void {
	recordActor();

	$a = makePackage( 'A' );
	makePackage( 'B' );

	$this->getJson( "/visual-editor/api/packages?include={$a->id},abc" )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.id', $a->id );
} );

it( 'returns a single record for a dynamic resource', function (): void {
	recordActor();

	$record = makePackage( 'Hooks', slug: 'hooks' );

	$this->getJson( "/visual-editor/api/packages/{$record->id}" )
		->assertOk()
		->assertExactJson( [ 'id' => $record->id, 'title' => 'Hooks', 'slug' => 'hooks' ] );
} );

it( 'returns 404 for an unregistered resource slug', function (): void {
	recordActor();

	$this->getJson( '/visual-editor/api/orders' )->assertNotFound();
	$this->getJson( '/visual-editor/api/orders/1' )->assertNotFound();
} );

it( 'returns 404 for a missing record', function (): void {
	recordActor();

	$this->getJson( '/visual-editor/api/packages/999' )->assertNotFound();
} );

it( 'leaves records the policy denies out of the list and forbids showing them', function (): void {
	recordActor();

	Gate::policy( TestGenericContentModel::class, TestPublishedOnlyPolicy::class );

	$published = makePackage( 'Published' );
	$draft     = makePackage( 'Draft', 'draft' );

	$this->getJson( '/visual-editor/api/packages' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.id', $published->id );

	$this->getJson( "/visual-editor/api/packages/{$draft->id}" )->assertForbidden();
} );

it( 'keeps posts on the WP-shape adapter instead of the generic route', function (): void {
	recordActor();

	$post = makePackage( 'Adapter' );

	$this->getJson( "/visual-editor/api/posts/{$post->id}" )
		->assertOk()
		->assertJsonPath( 'title.rendered', 'Adapter' );
} );
