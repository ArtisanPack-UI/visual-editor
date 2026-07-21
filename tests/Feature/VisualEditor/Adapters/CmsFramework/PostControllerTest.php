<?php

declare( strict_types=1 );

/**
 * Feature tests for the G3 PostController — WP-shape REST surface for
 * cms-framework's `Post`. Exercised against `TestBlockContentModel`
 * (which uses the same `HasBlockContent` trait) so the contract
 * proofs don't require pulling cms-framework into dev deps.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestG3Policy;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	Gate::policy( TestBlockContentModel::class, TestG3Policy::class );
} );

function actor(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Tester',
		'email'    => 'tester+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function blockTree(): array
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
	$post = TestBlockContentModel::create( [
		'title'   => 'Hello',
		'status'  => 'published',
		'content' => blockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$post->id}" )
		->assertUnauthorized();
} );

it( 'returns a single post in the WP-shape envelope', function () {
	actor();

	$post = TestBlockContentModel::create( [
		'title'   => 'Round-Trip',
		'status'  => 'published',
		'content' => blockTree(),
	] );

	$this->getJson( "/visual-editor/api/posts/{$post->id}" )
		->assertOk()
		->assertJsonPath( 'id', $post->id )
		->assertJsonPath( 'title.rendered', 'Round-Trip' )
		->assertJsonPath( 'title.raw', 'Round-Trip' )
		->assertJsonPath( 'type', 'post' )
		->assertJsonPath( 'status', 'published' )
		->assertJsonPath( 'content.blocks.0.name', 'core/paragraph' )
		->assertJsonPath( 'content.raw', '' );
} );

it( 'lists posts with the paginated { data, meta } envelope', function () {
	actor();

	for ( $i = 1; $i <= 3; $i++ ) {
		TestBlockContentModel::create( [
			'title'   => "Post {$i}",
			'status'  => 'published',
			'content' => [],
		] );
	}

	$this->getJson( '/visual-editor/api/posts' )
		->assertOk()
		->assertJsonCount( 3, 'data' )
		->assertJsonStructure( [
			'data'  => [ [ 'id', 'title', 'type', 'content' ] ],
			'meta'  => [ 'current_page', 'last_page', 'per_page', 'total' ],
			'links' => [ 'first', 'last' ],
		] )
		->assertJsonPath( 'meta.total', 3 );
} );

it( 'creates a post via POST and returns 201', function () {
	actor();

	$payload = [
		'title'   => 'Brand New',
		'status'  => 'published',
		'content' => [
			'raw'    => '',
			'blocks' => blockTree(),
		],
	];

	$response = $this->postJson( '/visual-editor/api/posts', $payload )
		->assertCreated()
		->assertJsonPath( 'title.rendered', 'Brand New' )
		->assertJsonPath( 'content.blocks.0.attributes.content', 'Hello world' );

	$id = $response->json( 'id' );

	$saved = TestBlockContentModel::find( $id );
	expect( $saved )->not->toBeNull();
	expect( $saved->getBlockContent() )->toEqual( blockTree() );
} );

it( 'updates a post via PUT and round-trips the block tree', function () {
	actor();

	$post = TestBlockContentModel::create( [
		'title'   => 'Original',
		'status'  => 'published',
		'content' => [],
	] );

	$next = [
		[
			'clientId'    => 'h1',
			'name'        => 'core/heading',
			'attributes'  => [ 'content' => 'Updated', 'level' => 2 ],
			'innerBlocks' => [],
		],
	];

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'title'   => 'Updated Title',
		'content' => [ 'raw' => '', 'blocks' => $next ],
	] )
		->assertOk()
		->assertJsonPath( 'title.rendered', 'Updated Title' )
		->assertJsonPath( 'content.blocks.0.name', 'core/heading' );

	expect( $post->fresh()->getBlockContent() )->toEqual( $next );
} );

it( 'persists a partial metadata-only PUT (excerpt + featured_media)', function () {
	actor();

	$post = TestBlockContentModel::create( [
		'title'   => 'Original',
		'status'  => 'published',
		'content' => [],
	] );

	// Mirror the shape that visual-editor's `saveEntityRecord` API
	// client sends when only sidebar metadata edits are pending — no
	// `content` envelope, just the staged fields.
	$this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'excerpt'        => 'A brand-new excerpt',
		'featured_media' => 42,
	] )
		->assertOk()
		->assertJsonPath( 'excerpt.rendered', 'A brand-new excerpt' );

	$fresh = $post->fresh();
	expect( $fresh->getAttribute( 'excerpt' ) )->toBe( 'A brand-new excerpt' );
	expect( (int) $fresh->getAttribute( 'featured_image_id' ) )->toBe( 42 );
} );

it( 'rejects a bare-list content payload with 422', function () {
	actor();

	$post = TestBlockContentModel::create( [
		'title'   => 'Original',
		'status'  => 'published',
		'content' => [],
	] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'content' => blockTree(), // bare list, not the { raw, blocks } envelope
	] )
		->assertStatus( 422 )
		->assertJsonValidationErrors( 'content' );
} );

it( 'deletes a post via DELETE and returns 204', function () {
	actor();

	$post = TestBlockContentModel::create( [
		'title'   => 'Doomed',
		'status'  => 'published',
		'content' => [],
	] );

	$this->deleteJson( "/visual-editor/api/posts/{$post->id}" )
		->assertNoContent();

	expect( TestBlockContentModel::find( $post->id ) )->toBeNull();
} );

it( 'returns 404 for a missing post', function () {
	actor();

	$this->getJson( '/visual-editor/api/posts/9999' )->assertNotFound();
} );

describe( 'lifecycle hooks', function (): void {
	afterEach( function (): void {
		removeAllActions( 'ap.visualEditor.postSaved' );
		removeAllActions( 'ap.visualEditor.postPublished' );
	} );

	it( 'fires ap.visualEditor.postSaved on POST /posts', function (): void {
		actor();

		$captured = [];
		addAction( 'ap.visualEditor.postSaved', function ( $id, $blocks ) use ( &$captured ): void {
			$captured = [ 'id' => $id, 'blocks' => $blocks ];
		}, 10, 2 );

		$this->postJson( '/visual-editor/api/posts', [
			'title'   => 'Fresh',
			'status'  => 'draft',
			'content' => [ 'raw' => '', 'blocks' => blockTree() ],
		] )->assertCreated();

		expect( $captured )->not->toBeEmpty()
			->and( $captured['id'] )->toBeInt()
			->and( $captured['blocks'] )->toEqual( blockTree() );
	} );

	it( 'fires postPublished on create-with-publish-status', function (): void {
		actor();

		$publishedCalls = 0;
		addAction( 'ap.visualEditor.postPublished', function ( $id, $blocks ) use ( &$publishedCalls ): void {
			$publishedCalls++;
		}, 10, 2 );

		$this->postJson( '/visual-editor/api/posts', [
			'title'   => 'Live',
			'status'  => 'publish',
			'content' => [ 'raw' => '', 'blocks' => blockTree() ],
		] )->assertCreated();

		expect( $publishedCalls )->toBe( 1 );
	} );

	it( 'does not fire postPublished when creating with a non-publish status', function (): void {
		actor();

		$publishedCalls = 0;
		addAction( 'ap.visualEditor.postPublished', function () use ( &$publishedCalls ): void {
			$publishedCalls++;
		} );

		$this->postJson( '/visual-editor/api/posts', [
			'title'   => 'Draft',
			'status'  => 'draft',
			'content' => [ 'raw' => '', 'blocks' => blockTree() ],
		] )->assertCreated();

		expect( $publishedCalls )->toBe( 0 );
	} );

	it( 'fires postPublished when the update transitions status into publish', function (): void {
		actor();

		// The fixture's `forVisualEditor()` scope filters to
		// `status === 'published'`, so we can only exercise the update
		// path with rows that pass the scope. Transitioning that row to
		// the WP-canonical `publish` on update still crosses the
		// non-publish → publish boundary the hook is meant to catch.
		$post = TestBlockContentModel::create( [
			'title'   => 'Was Not Publish',
			'status'  => 'published',
			'content' => [],
		] );

		$publishedPayloads = [];
		$savedPayloads     = [];
		addAction( 'ap.visualEditor.postPublished', function ( $id, $blocks ) use ( &$publishedPayloads ): void {
			$publishedPayloads[] = [ 'id' => $id, 'blocks' => $blocks ];
		}, 10, 2 );
		addAction( 'ap.visualEditor.postSaved', function ( $id, $blocks ) use ( &$savedPayloads ): void {
			$savedPayloads[] = [ 'id' => $id, 'blocks' => $blocks ];
		}, 10, 2 );

		$this->putJson( "/visual-editor/api/posts/{$post->id}", [
			'status'  => 'publish',
			'content' => [ 'raw' => '', 'blocks' => blockTree() ],
		] )->assertOk();

		expect( $publishedPayloads )->toHaveCount( 1 )
			->and( $savedPayloads )->toHaveCount( 1 )
			->and( $publishedPayloads[0]['id'] )->toBe( $post->id )
			->and( $publishedPayloads[0]['blocks'] )->toEqual( blockTree() )
			->and( $savedPayloads[0]['id'] )->toBe( $post->id )
			->and( $savedPayloads[0]['blocks'] )->toEqual( blockTree() );
	} );

	it( 'fires postSaved without postPublished when an update makes no status transition', function (): void {
		actor();

		$post = TestBlockContentModel::create( [
			'title'   => 'Content-only',
			'status'  => 'published',
			'content' => [],
		] );

		$savedCalls     = 0;
		$publishedCalls = 0;
		addAction( 'ap.visualEditor.postSaved', function () use ( &$savedCalls ): void {
			$savedCalls++;
		} );
		addAction( 'ap.visualEditor.postPublished', function () use ( &$publishedCalls ): void {
			$publishedCalls++;
		} );

		$this->putJson( "/visual-editor/api/posts/{$post->id}", [
			'title'   => 'Content-only (revised)',
			'content' => [ 'raw' => '', 'blocks' => blockTree() ],
		] )->assertOk();

		expect( $savedCalls )->toBe( 1 )
			->and( $publishedCalls )->toBe( 0 );
	} );

	it( 'does not re-fire postPublished when status stays at the same publish value', function (): void {
		actor();

		$post = TestBlockContentModel::create( [
			'title'   => 'Already',
			'status'  => 'published',
			'content' => [],
		] );

		$publishedCalls = 0;
		addAction( 'ap.visualEditor.postPublished', function () use ( &$publishedCalls ): void {
			$publishedCalls++;
		} );

		// Same status on both sides — no transition — so no publish event.
		$this->putJson( "/visual-editor/api/posts/{$post->id}", [
			'title'   => 'Already (revised)',
			'status'  => 'published',
			'content' => [ 'raw' => '', 'blocks' => blockTree() ],
		] )->assertOk();

		expect( $publishedCalls )->toBe( 0 );
	} );
} );
