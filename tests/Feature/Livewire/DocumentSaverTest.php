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

use Livewire\Livewire;

it( 'mounts with document ID', function (): void {
	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 42 ] )
		->assertSet( 'documentId', 42 )
		->assertSet( 'form.documentId', 42 );
} );

it( 'mounts without document ID', function (): void {
	Livewire::test( 'visual-editor::document-saver' )
		->assertSet( 'documentId', null )
		->assertSet( 'form.documentId', null );
} );

it( 'saves and dispatches document saved event', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 1 ] )
		->call( 'save', $blocks, 'draft' )
		->assertDispatched( 've-document-saved' );
} );

it( 'validates blocks are required', function (): void {
	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 1 ] )
		->call( 'save', [], 'draft' )
		->assertHasErrors( [ 'form.blocks' ] );
} );

it( 'validates document status', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 1 ] )
		->call( 'save', $blocks, 'invalid-status' )
		->assertHasErrors( [ 'form.documentStatus' ] );
} );

it( 'accepts valid document statuses', function ( string $status ): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 1 ] )
		->call( 'save', $blocks, $status )
		->assertHasNoErrors();
} )->with( [ 'draft', 'published', 'scheduled', 'pending' ] );

it( 'handles autosave event', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Auto saved' ] ],
	];

	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 1 ] )
		->dispatch( 've-autosave', blocks: $blocks )
		->assertDispatched( 've-document-saved' );
} );

it( 'dispatches error event on autosave failure', function (): void {
	Livewire::test( 'visual-editor::document-saver', [ 'documentId' => 1 ] )
		->dispatch( 've-autosave', blocks: [] )
		->assertDispatched( 've-document-error' );
} );
