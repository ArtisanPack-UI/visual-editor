<?php

/**
 * Revision History Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Stubs\AllowDocumentPolicy;
use Tests\Stubs\AllowRevisionPolicy;
use Tests\Stubs\DenyDocumentPolicy;
use Tests\Stubs\DenyRevisionPolicy;
use Tests\Stubs\FakeDocument;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	config()->set( 'artisanpack.visual-editor.user_model', User::class );
} );

it( 'mounts with document type and ID', function (): void {
	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 5,
	] )
		->assertSet( 'documentType', 'post' )
		->assertSet( 'documentId', 5 )
		->assertSet( 'showPanel', false );
} );

it( 'toggles the panel visibility', function (): void {
	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
	] )
		->assertSet( 'showPanel', false )
		->call( 'togglePanel' )
		->assertSet( 'showPanel', true )
		->call( 'togglePanel' )
		->assertSet( 'showPanel', false );
} );

it( 'renders revision list when panel is open', function (): void {
	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'created_at'    => now(),
	] );

	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
	] )
		->call( 'togglePanel' )
		->assertDontSee( __( 'visual-editor::ve.no_revisions' ) );
} );

it( 'shows revision list immediately when inline is true', function (): void {
	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Inline test' ] ] ],
		'created_at'    => now(),
	] );

	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
		'inline'       => true,
	] )
		->assertSet( 'showPanel', true )
		->assertDontSee( __( 'visual-editor::ve.no_revisions' ) );
} );

it( 'shows empty state when no revisions exist', function (): void {
	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
	] )
		->call( 'togglePanel' )
		->assertSee( __( 'visual-editor::ve.no_revisions' ) );
} );

it( 'refreshes revisions on ve-document-saved', function (): void {
	$component = Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
		'inline'       => true,
	] )
		->assertSee( __( 'visual-editor::ve.no_revisions' ) );

	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'created_at'    => now(),
	] );

	$component
		->dispatch( 've-document-saved', documentId: 1, scheduledDate: null )
		->assertDontSee( __( 'visual-editor::ve.no_revisions' ) );
} );

it( 'restores a revision and dispatches event', function (): void {
	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'content' => 'Old Title' ] ],
	];

	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => $blocks,
		'created_at'    => now(),
	] );

	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
	] )
		->call( 'restoreRevision', $revision->id )
		->assertDispatched( 've-revision-restored' );
} );

it( 'denies restore when policy forbids it', function (): void {
	Gate::policy( Revision::class, DenyRevisionPolicy::class );

	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Locked' ] ] ],
		'created_at'    => now(),
	] );

	Livewire::actingAs( new User() )
		->test( 'visual-editor::revision-history', [
			'documentType' => 'post',
			'documentId'   => 1,
		] )
		->call( 'restoreRevision', $revision->id )
		->assertForbidden();
} );

it( 'allows restore when policy permits it', function (): void {
	Gate::policy( Revision::class, AllowRevisionPolicy::class );

	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	Livewire::actingAs( new User() )
		->test( 'visual-editor::revision-history', [
			'documentType' => 'post',
			'documentId'   => 1,
		] )
		->call( 'restoreRevision', $revision->id )
		->assertDispatched( 've-revision-restored' );
} );

it( 'denies delete when policy forbids it', function (): void {
	Gate::policy( Revision::class, DenyRevisionPolicy::class );

	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	Livewire::actingAs( new User() )
		->test( 'visual-editor::revision-history', [
			'documentType' => 'post',
			'documentId'   => 1,
		] )
		->call( 'deleteRevision', $revision->id )
		->assertForbidden();

	expect( Revision::find( $revision->id ) )->not->toBeNull();
} );

it( 'allows delete when policy permits it', function (): void {
	Gate::policy( Revision::class, AllowRevisionPolicy::class );

	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	Livewire::actingAs( new User() )
		->test( 'visual-editor::revision-history', [
			'documentType' => 'post',
			'documentId'   => 1,
		] )
		->call( 'deleteRevision', $revision->id )
		->assertDispatched( 've-revision-deleted' );

	expect( Revision::find( $revision->id ) )->toBeNull();
} );

it( 'deletes a revision when no policy is registered', function (): void {
	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
	] )
		->call( 'deleteRevision', $revision->id )
		->assertDispatched( 've-revision-deleted' );

	expect( Revision::find( $revision->id ) )->toBeNull();
} );

describe( 'document policy fallback authorization', function (): void {
	beforeEach( function (): void {
		Schema::create( 'fake_documents', function ( Illuminate\Database\Schema\Blueprint $table ): void {
			$table->id();
			$table->timestamps();
		} );
	} );

	afterEach( function (): void {
		Schema::dropIfExists( 'fake_documents' );
	} );

	it( 'denies restore when no revision policy exists but document policy denies update', function (): void {
		$document = FakeDocument::create();

		Gate::policy( FakeDocument::class, DenyDocumentPolicy::class );

		$revision = Revision::create( [
			'document_type' => FakeDocument::class,
			'document_id'   => $document->id,
			'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
			'created_at'    => now(),
		] );

		Livewire::actingAs( new User() )
			->test( 'visual-editor::revision-history', [
				'documentType' => FakeDocument::class,
				'documentId'   => $document->id,
			] )
			->call( 'restoreRevision', $revision->id )
			->assertForbidden();
	} );

	it( 'allows restore when no revision policy exists but document policy allows update', function (): void {
		$document = FakeDocument::create();

		Gate::policy( FakeDocument::class, AllowDocumentPolicy::class );

		$revision = Revision::create( [
			'document_type' => FakeDocument::class,
			'document_id'   => $document->id,
			'blocks'        => [],
			'created_at'    => now(),
		] );

		Livewire::actingAs( new User() )
			->test( 'visual-editor::revision-history', [
				'documentType' => FakeDocument::class,
				'documentId'   => $document->id,
			] )
			->call( 'restoreRevision', $revision->id )
			->assertDispatched( 've-revision-restored' );
	} );

	it( 'denies delete when no revision policy exists but document policy denies update', function (): void {
		$document = FakeDocument::create();

		Gate::policy( FakeDocument::class, DenyDocumentPolicy::class );

		$revision = Revision::create( [
			'document_type' => FakeDocument::class,
			'document_id'   => $document->id,
			'blocks'        => [],
			'created_at'    => now(),
		] );

		Livewire::actingAs( new User() )
			->test( 'visual-editor::revision-history', [
				'documentType' => FakeDocument::class,
				'documentId'   => $document->id,
			] )
			->call( 'deleteRevision', $revision->id )
			->assertForbidden();

		expect( Revision::find( $revision->id ) )->not->toBeNull();
	} );

	it( 'allows delete when no revision policy exists but document policy allows update', function (): void {
		$document = FakeDocument::create();

		Gate::policy( FakeDocument::class, AllowDocumentPolicy::class );

		$revision = Revision::create( [
			'document_type' => FakeDocument::class,
			'document_id'   => $document->id,
			'blocks'        => [],
			'created_at'    => now(),
		] );

		Livewire::actingAs( new User() )
			->test( 'visual-editor::revision-history', [
				'documentType' => FakeDocument::class,
				'documentId'   => $document->id,
			] )
			->call( 'deleteRevision', $revision->id )
			->assertDispatched( 've-revision-deleted' );

		expect( Revision::find( $revision->id ) )->toBeNull();
	} );
} );

