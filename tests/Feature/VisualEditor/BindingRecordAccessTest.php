<?php

/**
 * The bindings resolve and block preview endpoints check the context
 * record's `view` policy when the model has one (F-13).
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBindingsModel;
use Tests\Fixtures\TestDynamicBlock;
use Tests\Fixtures\TestPublishedOnlyPolicy;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );
	config()->set( 'artisanpack.visual-editor.resources', [ 'bindings' => TestBindingsModel::class ] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	$this->actingAs( TestUser::create( [
		'name'     => 'Record access tester',
		'email'    => 'record-access+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );

	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );
} );

/**
 * A bindings resolve payload bound to the record's title.
 *
 * @return array<string, mixed>
 */
function recordAccessResolvePayload( int $id ): array
{
	return [
		'attrs'    => [ 'label' => 'Fallback' ],
		'bindings' => [ 'label' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'title' ] ] ],
		'context'  => [ 'resource' => 'bindings', 'id' => $id ],
	];
}

/**
 * A block preview payload bound to the record's title.
 *
 * @return array<string, mixed>
 */
function recordAccessPreviewPayload( int $id ): array
{
	return [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Static', 'greeting' => 'Hi' ],
		'bindings'   => [ 'name' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'title' ] ] ],
		'context'    => [ 'resource' => 'bindings', 'id' => $id ],
	];
}

function recordAccessModel( string $status ): TestBindingsModel
{
	return TestBindingsModel::query()->create( [ 'title' => 'Secret ' . $status, 'status' => $status, 'content' => [] ] );
}

it( 'resolves bindings against any record when its model has no policy', function (): void {
	$draft = recordAccessModel( 'draft' );

	$this->postJson( '/visual-editor/api/bindings/resolve', recordAccessResolvePayload( $draft->id ) )
		->assertOk()
		->assertJsonPath( 'values.label', 'Secret draft' );

	$this->postJson( '/visual-editor/api/blocks/preview', recordAccessPreviewPayload( $draft->id ) )
		->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Secret draft!</p>' );
} );

it( 'returns 403 when the model\'s policy denies view', function (): void {
	Gate::policy( TestBindingsModel::class, TestPublishedOnlyPolicy::class );

	$draft = recordAccessModel( 'draft' );

	$this->postJson( '/visual-editor/api/bindings/resolve', recordAccessResolvePayload( $draft->id ) )->assertForbidden();
	$this->postJson( '/visual-editor/api/blocks/preview', recordAccessPreviewPayload( $draft->id ) )->assertForbidden();
} );

it( 'resolves when the model\'s policy allows view', function (): void {
	Gate::policy( TestBindingsModel::class, TestPublishedOnlyPolicy::class );

	$published = recordAccessModel( 'published' );

	$this->postJson( '/visual-editor/api/bindings/resolve', recordAccessResolvePayload( $published->id ) )
		->assertOk()
		->assertJsonPath( 'values.label', 'Secret published' );

	$this->postJson( '/visual-editor/api/blocks/preview', recordAccessPreviewPayload( $published->id ) )
		->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Secret published!</p>' );
} );

it( 'returns a per-item error for a forbidden record in a batch without failing the others', function (): void {
	Gate::policy( TestBindingsModel::class, TestPublishedOnlyPolicy::class );

	$draft     = recordAccessModel( 'draft' );
	$published = recordAccessModel( 'published' );

	$this->postJson( '/visual-editor/api/bindings/resolve', [
		'items' => [
			[ 'key' => 'draft' ] + recordAccessResolvePayload( $draft->id ),
			[ 'key' => 'published' ] + recordAccessResolvePayload( $published->id ),
			[ 'key' => 'draft-again' ] + recordAccessResolvePayload( $draft->id ),
		],
	] )
		->assertOk()
		->assertJsonPath( 'results.0.key', 'draft' )
		->assertJsonPath( 'results.0.error', 'forbidden' )
		->assertJsonMissingPath( 'results.0.values' )
		->assertJsonPath( 'results.1.values.label', 'Secret published' )
		->assertJsonPath( 'results.2.error', 'forbidden' );
} );
