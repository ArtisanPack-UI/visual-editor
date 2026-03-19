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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	config()->set( 'artisanpack.visual-editor.user_model', Illuminate\Foundation\Auth\User::class );
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
	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
		'created_at'    => now(),
	] );

	Livewire::test( 'visual-editor::revision-history', [
		'documentType' => 'post',
		'documentId'   => 1,
		'inline'       => true,
	] )
		->assertDontSee( __( 'visual-editor::ve.no_revisions' ) )
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

it( 'deletes a revision', function (): void {
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

