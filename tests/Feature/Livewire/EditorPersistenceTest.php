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

use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * Build the expected guest cache key for a given document ID.
 *
 * @param string $documentId The document identifier.
 *
 * @return string
 */
function guestCacheKey( string $documentId ): string
{
	return 've-draft-guest-' . session()->getId() . '-' . $documentId;
}

it( 'mounts with document ID', function (): void {
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'new-post' ] )
		->assertSet( 'documentId', 'new-post' )
		->assertSet( 'hasDraft', false );
} );

it( 'rejects empty document ID', function (): void {
	$this->expectException( Illuminate\View\ViewException::class );
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => '' ] );
} );

it( 'saves draft to cache', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Draft content' ] ],
	];

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'test-doc' ] )
		->dispatch( 've-autosave', blocks: $blocks )
		->assertSet( 'hasDraft', true )
		->assertDispatched( 've-draft-saved' );

	expect( Cache::has( guestCacheKey( 'test-doc' ) ) )->toBeTrue();
} );

it( 'restores draft and dispatches event', function (): void {
	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'content' => 'Restored' ] ],
	];

	Cache::put( guestCacheKey( 'restore-doc' ), $blocks, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'restore-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'restoreDraft' )
		->assertDispatched( 've-draft-restored' );
} );

it( 'discards draft and clears cache', function (): void {
	Cache::put( guestCacheKey( 'discard-doc' ), [ [ 'type' => 'paragraph' ] ], 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'discard-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'discardDraft' )
		->assertSet( 'hasDraft', false )
		->assertDispatched( 've-draft-discarded' );

	expect( Cache::has( guestCacheKey( 'discard-doc' ) ) )->toBeFalse();
} );

it( 'does not dispatch when no draft exists', function (): void {
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'empty-doc' ] )
		->assertSet( 'hasDraft', false )
		->call( 'restoreDraft' )
		->assertNotDispatched( 've-draft-restored' );
} );

it( 'respects config TTL', function (): void {
	config()->set( 'artisanpack.visual-editor.persistence.draft_ttl', 3600 );

	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'TTL test' ] ],
	];

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'ttl-doc' ] )
		->dispatch( 've-autosave', blocks: $blocks )
		->assertDispatched( 've-draft-saved' );

	expect( Cache::has( guestCacheKey( 'ttl-doc' ) ) )->toBeTrue();
} );
