<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Controllers\BindingResolveController;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	config()->set( 'artisanpack.visual-editor.resources', [
		'bindings' => TestBindingsModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	$this->actor = TestUser::create( [
		'name'     => 'Resolve Tester',
		'email'    => 'resolve+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'resolves a binding to the parent model column value', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Bound Title',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC', 'label' => 'Original' ],
		'bindings' => [
			'icon' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.icon', 'Bound Title' );
} );

it( 'returns the static fallback when the bound field is empty', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Has Title',
		'status'  => 'published',
		'excerpt' => null,
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'label' => 'Fallback' ],
		'bindings' => [
			'label' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'excerpt' ],
			],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.label', 'Fallback' );
} );

it( 'returns an empty values object when no bindings are supplied', function (): void {
	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC' ],
		'bindings' => [],
		'context'  => [],
	] );

	$response->assertOk()
		->assertJsonPath( 'values', [] );
} );

it( 'returns the fallback value when no context is supplied', function (): void {
	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC' ],
		'bindings' => [
			'icon' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.icon', 'STATIC' );
} );

it( 'rejects payloads where attrs or bindings is not an array', function (): void {
	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => 'not-an-array',
		'bindings' => [],
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [],
		'bindings' => 'not-an-array',
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );
} );

it( 'reflects draft overrides ahead of saved column values', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Saved Title',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC' ],
		'bindings' => [
			'icon' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
			'draft'    => [ 'title' => 'Live Draft' ],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.icon', 'Live Draft' );
} );

it( 'resolves multiple bindings on the same block in one round-trip', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'My Title',
		'excerpt' => 'My Excerpt',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'a' => 'a-static', 'b' => 'b-static' ],
		'bindings' => [
			'a' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
			'b' => [ 'source' => 'post_core', 'args' => [ 'key' => 'excerpt' ] ],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.a', 'My Title' )
		->assertJsonPath( 'values.b', 'My Excerpt' );
} );

/**
 * One batch item bound to a record column.
 *
 * @return array<string, mixed>
 */
function batchResolveItem( string $key, string $column, ?int $id, string $fallback = 'STATIC' ): array
{
	return [
		'key'      => $key,
		'attrs'    => [ 'label' => $fallback ],
		'bindings' => [ 'label' => [ 'source' => 'custom_field', 'args' => [ 'key' => $column ] ] ],
		'context'  => [ 'resource' => 'bindings', 'id' => $id ],
	];
}

it( 'resolves a batch of items in one request, in request order', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Batch Title',
		'excerpt' => 'Batch Excerpt',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'items' => [
			batchResolveItem( 'b', 'excerpt', $model->getKey() ),
			batchResolveItem( 'a', 'title', $model->getKey() ),
		],
	] );

	$response->assertOk()
		->assertJsonCount( 2, 'results' )
		->assertJsonPath( 'results.0.key', 'b' )
		->assertJsonPath( 'results.0.values.label', 'Batch Excerpt' )
		->assertJsonPath( 'results.1.key', 'a' )
		->assertJsonPath( 'results.1.values.label', 'Batch Title' );
} );

it( 'loads each context record once per batch', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Shared Record',
		'status'  => 'published',
		'content' => [],
	] );

	$items = [];

	for ( $i = 0; $i < 10; $i++ ) {
		$items[] = batchResolveItem( 'item-' . $i, 'title', $model->getKey() );
	}

	$table = $model->getTable();

	DB::enableQueryLog();

	$this->postJson( '/visual-editor/api/bindings/resolve', [ 'items' => $items ] )
		->assertOk()
		->assertJsonCount( 10, 'results' )
		->assertJsonPath( 'results.9.values.label', 'Shared Record' );

	$recordQueries = array_filter(
		DB::getQueryLog(),
		static fn ( array $query ): bool => str_contains( $query['query'], $table ),
	);

	expect( $recordQueries )->toHaveCount( 1 );
} );

it( 'resolves items with mixed contexts in one batch', function (): void {
	$first  = TestBindingsModel::query()->create( [ 'title' => 'First', 'status' => 'published', 'content' => [] ] );
	$second = TestBindingsModel::query()->create( [ 'title' => 'Second', 'status' => 'published', 'content' => [] ] );

	$drafted                     = batchResolveItem( 'drafted', 'title', $first->getKey() );
	$drafted['context']['draft'] = [ 'title' => 'First Draft' ];

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'items' => [
			batchResolveItem( 'first', 'title', $first->getKey() ),
			batchResolveItem( 'second', 'title', $second->getKey() ),
			$drafted,
			batchResolveItem( 'missing', 'title', 999999, 'Fallback' ),
			batchResolveItem( 'no-context', 'title', null, 'No Context' ),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'results.0.values.label', 'First' )
		->assertJsonPath( 'results.1.values.label', 'Second' )
		->assertJsonPath( 'results.2.values.label', 'First Draft' )
		->assertJsonPath( 'results.3.values.label', 'Fallback' )
		->assertJsonPath( 'results.4.values.label', 'No Context' );
} );

it( 'returns per-item errors for malformed items without failing the batch', function (): void {
	$model = TestBindingsModel::query()->create( [ 'title' => 'Good', 'status' => 'published', 'content' => [] ] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'items' => [
			[ 'attrs' => [], 'bindings' => [] ],
			[ 'key' => 'bad-attrs', 'attrs' => 'nope', 'bindings' => [] ],
			[ 'key' => 'empty', 'attrs' => [ 'label' => 'x' ], 'bindings' => [] ],
			batchResolveItem( 'good', 'title', $model->getKey() ),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'results.0.key', null )
		->assertJsonPath( 'results.0.error', 'invalid_item' )
		->assertJsonPath( 'results.1.key', 'bad-attrs' )
		->assertJsonPath( 'results.1.error', 'invalid_item' )
		->assertJsonPath( 'results.2.key', 'empty' )
		->assertJsonPath( 'results.2.values', [] )
		->assertJsonPath( 'results.3.values.label', 'Good' );
} );

it( 'rejects a batch that is not a list', function (): void {
	$this->postJson( '/visual-editor/api/bindings/resolve', [ 'items' => 'nope' ] )
		->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );

	$this->postJson( '/visual-editor/api/bindings/resolve', [ 'items' => [ 'a' => batchResolveItem( 'a', 'title', null ) ] ] )
		->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );
} );

it( 'rejects a batch over the item cap', function (): void {
	$items = [];

	for ( $i = 0; $i <= BindingResolveController::MAX_ITEMS; $i++ ) {
		$items[] = batchResolveItem( 'item-' . $i, 'title', null );
	}

	$this->postJson( '/visual-editor/api/bindings/resolve', [ 'items' => $items ] )
		->assertStatus( 422 )
		->assertJsonPath( 'error', 'too_many_items' );
} );

it( 'returns an empty results list for an empty batch', function (): void {
	$this->postJson( '/visual-editor/api/bindings/resolve', [ 'items' => [] ] )
		->assertOk()
		->assertJsonPath( 'results', [] );
} );
