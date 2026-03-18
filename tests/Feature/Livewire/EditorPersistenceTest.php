<?php

/**
 * Editor Persistence Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

/**
 * Build the expected guest cache key for a given document type and ID.
 *
 * @param string $documentType The document type (model class).
 * @param string $documentId   The document identifier.
 *
 * @return string
 */
function guestCacheKey( string $documentType, string $documentId ): string
{
	return 've-draft-' . $documentType . '-' . $documentId . '-guest-' . session()->getId();
}

/**
 * Build an authenticated user cache key for a given document type and ID.
 *
 * @param int    $userId       The user ID.
 * @param string $documentType The document type (model class).
 * @param string $documentId   The document identifier.
 *
 * @return string
 */
function authCacheKey( int $userId, string $documentType, string $documentId ): string
{
	return 've-draft-' . $documentType . '-' . $documentId . '-' . $userId;
}

/**
 * Create a test user with the given email.
 *
 * @param string $email The email address.
 *
 * @return Authenticatable
 */
function createPersistenceUser( string $email = 'test@example.com' ): Authenticatable
{
	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Test User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Test User';
	$user->email = $email;

	return $user;
}

it( 'mounts with document type and ID', function (): void {
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'new-post' ] )
		->assertSet( 'documentType', 'App\\Models\\Post' )
		->assertSet( 'documentId', 'new-post' )
		->assertSet( 'hasDraft', false );
} );

it( 'rejects empty document type', function (): void {
	$this->expectException( Illuminate\View\ViewException::class );
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => '', 'documentId' => 'test' ] );
} );

it( 'rejects empty document ID', function (): void {
	$this->expectException( Illuminate\View\ViewException::class );
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => '' ] );
} );

it( 'saves draft with blocks and meta to cache', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Draft content' ] ],
	];
	$meta = [ 'title' => 'My Draft', 'status' => 'draft' ];

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'test-doc' ] )
		->dispatch( 've-autosave', blocks: $blocks, meta: $meta )
		->assertSet( 'hasDraft', true )
		->assertDispatched( 've-draft-saved' );

	$cached = Cache::get( guestCacheKey( 'App\\Models\\Post', 'test-doc' ) );

	expect( $cached )->toBeArray()
		->and( $cached['blocks'] )->toHaveCount( 1 )
		->and( $cached['meta'] )->toBe( [ 'title' => 'My Draft', 'status' => 'draft' ] );
} );

it( 'saves draft with blocks only when meta is omitted', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'No meta' ] ],
	];

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'no-meta-doc' ] )
		->dispatch( 've-autosave', blocks: $blocks )
		->assertSet( 'hasDraft', true )
		->assertDispatched( 've-draft-saved' );

	$cached = Cache::get( guestCacheKey( 'App\\Models\\Post', 'no-meta-doc' ) );

	expect( $cached['blocks'] )->toHaveCount( 1 )
		->and( $cached['meta'] )->toBe( [] );
} );

it( 'dispatches ve-draft-found on mount when draft exists', function (): void {
	$draft = [
		'blocks' => [ [ 'type' => 'heading', 'attributes' => [ 'content' => 'Recovered' ] ] ],
		'meta'   => [ 'title' => 'Recovered Title' ],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'auto-load-doc' ), $draft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'auto-load-doc' ] )
		->assertSet( 'hasDraft', true )
		->assertDispatched( 've-draft-found' )
		->assertNotDispatched( 've-draft-restored' );
} );

it( 'restores draft with matching blocks and meta payload', function (): void {
	$draft = [
		'blocks' => [ [ 'type' => 'heading', 'attributes' => [ 'content' => 'Restored' ] ] ],
		'meta'   => [ 'title' => 'Restored Title' ],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'restore-doc' ), $draft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'restore-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'restoreDraft' )
		->assertDispatched( 've-draft-restored', function ( string $event, array $params ): bool {
			return $params['blocks'] === [ [ 'type' => 'heading', 'attributes' => [ 'content' => 'Restored' ] ] ]
				&& $params['meta'] === [ 'title' => 'Restored Title' ];
		} );
} );

it( 'loadDraft dispatches matching blocks and meta payload', function (): void {
	$draft = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Loaded' ] ] ],
		'meta'   => [ 'documentStatus' => 'published', 'title' => 'Loaded Title' ],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'load-doc' ), $draft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'load-doc' ] )
		->call( 'loadDraft' )
		->assertDispatched( 've-draft-restored', function ( string $event, array $params ) use ( $draft ): bool {
			return $params['blocks'] === $draft['blocks']
				&& $params['meta'] === $draft['meta'];
		} );
} );

it( 'clears draft from cache via clearDraft', function (): void {
	$draft = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'To clear' ] ] ],
		'meta'   => [],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'clear-doc' ), $draft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'clear-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'clearDraft' )
		->assertSet( 'hasDraft', false );

	expect( Cache::has( guestCacheKey( 'App\\Models\\Post', 'clear-doc' ) ) )->toBeFalse();
} );

it( 'clears draft when ve-document-saved event is received', function (): void {
	$draft = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Saved' ] ] ],
		'meta'   => [],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'saved-doc' ), $draft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'saved-doc' ] )
		->assertSet( 'hasDraft', true )
		->dispatch( 've-document-saved', documentId: 1, scheduledDate: null )
		->assertSet( 'hasDraft', false );

	expect( Cache::has( guestCacheKey( 'App\\Models\\Post', 'saved-doc' ) ) )->toBeFalse();
} );

it( 'discards draft and dispatches event', function (): void {
	$draft = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Discard me' ] ] ],
		'meta'   => [],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'discard-doc' ), $draft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'discard-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'discardDraft' )
		->assertSet( 'hasDraft', false )
		->assertDispatched( 've-draft-discarded' );

	expect( Cache::has( guestCacheKey( 'App\\Models\\Post', 'discard-doc' ) ) )->toBeFalse();
} );

it( 'does not dispatch when no draft exists', function (): void {
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'empty-doc' ] )
		->assertSet( 'hasDraft', false )
		->call( 'restoreDraft' )
		->assertNotDispatched( 've-draft-restored' );
} );

it( 'respects config TTL when saving draft', function (): void {
	config()->set( 'artisanpack.visual-editor.persistence.draft_ttl', 3600 );

	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'TTL test' ] ],
	];

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'ttl-doc' ] )
		->dispatch( 've-autosave', blocks: $blocks )
		->assertDispatched( 've-draft-saved' );

	// Verify the draft was stored
	expect( Cache::has( guestCacheKey( 'App\\Models\\Post', 'ttl-doc' ) ) )->toBeTrue();

	// Verify the stored payload structure
	$cached = Cache::get( guestCacheKey( 'App\\Models\\Post', 'ttl-doc' ) );

	expect( $cached )->toBe( [ 'blocks' => $blocks, 'meta' => [] ] );
} );

it( 'scopes drafts by user and document type for guests', function (): void {
	$draftA = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Post draft' ] ] ],
		'meta'   => [],
	];
	$draftB = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Page draft' ] ] ],
		'meta'   => [],
	];

	Cache::put( guestCacheKey( 'App\\Models\\Post', 'doc-1' ), $draftA, 86400 );
	Cache::put( guestCacheKey( 'App\\Models\\Page', 'doc-1' ), $draftB, 86400 );

	// Post draft exists
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'doc-1' ] )
		->assertSet( 'hasDraft', true );

	// Page draft exists independently
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Page', 'documentId' => 'doc-1' ] )
		->assertSet( 'hasDraft', true );

	// No draft for a different document ID
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'doc-2' ] )
		->assertSet( 'hasDraft', false );
} );

it( 'isolates drafts between authenticated users', function (): void {
	$userA = createPersistenceUser( 'alice@example.com' );
	$userB = createPersistenceUser( 'bob@example.com' );

	$draft = [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Alice draft' ] ] ],
		'meta'   => [],
	];

	Cache::put( authCacheKey( $userA->id, 'App\\Models\\Post', 'doc-1' ), $draft, 86400 );

	// User A sees the draft
	Livewire::actingAs( $userA )
		->test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'doc-1' ] )
		->assertSet( 'hasDraft', true );

	// User B does not see User A's draft
	Livewire::actingAs( $userB )
		->test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'doc-1' ] )
		->assertSet( 'hasDraft', false );
} );

it( 'migrates legacy cache key to new format and normalizes raw blocks', function (): void {
	$legacyKey = 've-draft-guest-' . session()->getId() . '-legacy-doc';
	$rawBlocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Legacy draft' ] ],
	];

	Cache::put( $legacyKey, $rawBlocks, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'legacy-doc' ] )
		->assertSet( 'hasDraft', true );

	// Legacy key is deleted after migration
	expect( Cache::has( $legacyKey ) )->toBeFalse()
		->and( Cache::has( guestCacheKey( 'App\\Models\\Post', 'legacy-doc' ) ) )->toBeTrue();

	// Migrated data is normalized into { blocks, meta } shape
	$migrated = Cache::get( guestCacheKey( 'App\\Models\\Post', 'legacy-doc' ) );

	expect( $migrated )->toBe( [ 'blocks' => $rawBlocks, 'meta' => [] ] );
} );

it( 'does not overwrite new key when legacy key exists', function (): void {
	$legacyKey = 've-draft-guest-' . session()->getId() . '-overwrite-doc';
	$newKey    = guestCacheKey( 'App\\Models\\Post', 'overwrite-doc' );

	$legacyDraft = [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Old' ] ] ];
	$newDraft    = [ 'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'New' ] ] ], 'meta' => [] ];

	Cache::put( $legacyKey, $legacyDraft, 86400 );
	Cache::put( $newKey, $newDraft, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentType' => 'App\\Models\\Post', 'documentId' => 'overwrite-doc' ] )
		->assertSet( 'hasDraft', true );

	// Legacy key is still cleaned up
	expect( Cache::has( $legacyKey ) )->toBeFalse();

	// New key retains its original value (not overwritten by legacy)
	$current = Cache::get( $newKey );

	expect( $current['blocks'][0]['attributes']['content'] )->toBe( 'New' );
} );
