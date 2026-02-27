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

it( 'mounts with document ID', function (): void {
	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'new-post' ] )
		->assertSet( 'documentId', 'new-post' )
		->assertSet( 'hasDraft', false );
} );

it( 'saves draft to cache', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Draft content' ] ],
	];

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'test-doc' ] )
		->dispatch( 've-autosave', blocks: $blocks )
		->assertSet( 'hasDraft', true )
		->assertDispatched( 've-draft-saved' );

	expect( Cache::has( 've-draft-test-doc' ) )->toBeTrue();
} );

it( 'restores draft and dispatches event', function (): void {
	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'content' => 'Restored' ] ],
	];

	Cache::put( 've-draft-restore-doc', $blocks, 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'restore-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'restoreDraft' )
		->assertDispatched( 've-draft-restored' );
} );

it( 'discards draft and clears cache', function (): void {
	Cache::put( 've-draft-discard-doc', [ [ 'type' => 'paragraph' ] ], 86400 );

	Livewire::test( 'visual-editor::editor-persistence', [ 'documentId' => 'discard-doc' ] )
		->assertSet( 'hasDraft', true )
		->call( 'discardDraft' )
		->assertSet( 'hasDraft', false )
		->assertDispatched( 've-draft-discarded' );

	expect( Cache::has( 've-draft-discard-doc' ) )->toBeFalse();
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

	expect( Cache::has( 've-draft-ttl-doc' ) )->toBeTrue();
} );
