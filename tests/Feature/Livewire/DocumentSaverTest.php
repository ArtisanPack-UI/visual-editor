<?php

/**
 * Document Saver Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Unit\Concerns\Stubs\TestPost;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );

	Schema::create( 'test_posts', function ( $table ): void {
		$table->id();
		$table->json( 'blocks' )->nullable();
		$table->string( 'status' )->nullable();
		$table->timestamp( 'scheduled_at' )->nullable();
		$table->timestamps();
	} );
} );

it( 'mounts with a model implementing EditorContent', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->assertSet( 'form.documentId', $post->id );
} );

it( 'saves and persists blocks to the model', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertDispatched( 've-document-saved' );

	$post->refresh();

	expect( $post->blocks )->toHaveCount( 1 )
		->and( $post->blocks[0]['attributes']['content'] )->toBe( 'Hello' );
} );

it( 'saves with meta data and persists status', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
		'documentStatus' => 'published',
		'meta'           => [ 'title' => 'My Post', 'excerpt' => 'Summary' ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertSet( 'form.meta', [ 'title' => 'My Post', 'excerpt' => 'Summary' ] )
		->assertDispatched( 've-document-saved' );

	$post->refresh();

	expect( $post->status )->toBe( 'published' );
} );

it( 'saves scheduled date to the model', function (): void {
	$post          = TestPost::create( [ 'status' => 'draft' ] );
	$scheduledDate = now()->addDay()->toDateTimeString();

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Scheduled' ] ] ],
		'documentStatus' => 'scheduled',
		'scheduledDate'  => $scheduledDate,
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertDispatched( 've-document-saved' );

	$post->refresh();

	expect( $post->status )->toBe( 'scheduled' )
		->and( $post->scheduled_at )->not->toBeNull();
} );

it( 'creates a revision after saving', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Rev content' ] ] ],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload );

	expect( $post->revisions()->count() )->toBe( 1 );
} );

it( 'validates blocks are required', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertHasErrors( [ 'form.blocks' ] );
} );

it( 'validates document status', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'documentStatus' => 'invalid-status',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertHasErrors( [ 'form.documentStatus' ] );
} );

it( 'accepts valid document statuses', function ( string $status ): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$scheduledDate = 'scheduled' === $status ? now()->addDay()->toDateTimeString() : null;

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'documentStatus' => $status,
		'scheduledDate'  => $scheduledDate,
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertHasNoErrors();
} )->with( [ 'draft', 'published', 'scheduled', 'pending' ] );

it( 'requires scheduled date when status is scheduled', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'documentStatus' => 'scheduled',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertHasErrors( [ 'form.scheduledDate' ] );
} );

it( 'handles autosave and persists to model', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Auto saved' ] ] ],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'autosave', $payload )
		->assertDispatched( 've-document-saved' );

	$post->refresh();

	expect( $post->blocks )->toHaveCount( 1 )
		->and( $post->blocks[0]['attributes']['content'] )->toBe( 'Auto saved' );
} );

it( 'handles autosave with meta', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Auto saved' ] ] ],
		'documentStatus' => 'draft',
		'meta'           => [ 'title' => 'Autosaved Title' ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'autosave', $payload )
		->assertSet( 'form.meta', [ 'title' => 'Autosaved Title' ] )
		->assertDispatched( 've-document-saved' );
} );

it( 'dispatches error event on autosave failure', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'autosave', $payload )
		->assertDispatched( 've-document-error' );
} );

it( 'authorizes update when a policy exists', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	Gate::policy( TestPost::class, TestPostPolicy::class );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertForbidden();
} );

it( 'skips authorization when no policy exists', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'documentStatus' => 'draft',
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertHasNoErrors()
		->assertDispatched( 've-document-saved' );
} );

it( 'merges meta into the save payload', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks'         => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
		'documentStatus' => 'published',
		'meta'           => [ 'title' => 'Custom Title' ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertDispatched( 've-document-saved' );

	$post->refresh();

	expect( $post->blocks[0]['attributes']['content'] )->toBe( 'Hello' )
		->and( $post->status )->toBe( 'published' );
} );

it( 'handles payload with missing optional fields', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$payload = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Minimal' ] ] ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'model' => $post ] )
		->call( 'save', $payload )
		->assertHasNoErrors()
		->assertDispatched( 've-document-saved' );

	$post->refresh();

	expect( $post->blocks )->toHaveCount( 1 );
} );

/**
 * Test policy that denies all updates.
 *
 * @since 1.0.0
 */
class TestPostPolicy
{
	/**
	 * Determine if the user can update the post.
	 *
	 * @return Response
	 */
	public function update(): Response
	{
		return Response::deny( 'Not allowed.' );
	}
}
