<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use Livewire\Livewire;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user    = User::factory()->create();
	$this->content = Content::create( [
		'title'     => 'Test Page',
		'slug'      => 'test-page',
		'sections'  => [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ],
		'settings'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );
} );

test( 'editor component renders successfully', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertStatus( 200 );
} );

test( 'editor initializes with content data', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'sidebarOpen', true )
		->assertSet( 'sidebarTab', 'blocks' )
		->assertSet( 'isDirty', false )
		->assertSet( 'saveStatus', 'saved' )
		->assertSet( 'activeBlockId', null );
} );

test( 'editor loads sections from content', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'sections', $this->content->sections );
} );

test( 'editor can toggle sidebar', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'sidebarOpen', true )
		->call( 'toggleSidebar' )
		->assertSet( 'sidebarOpen', false )
		->call( 'toggleSidebar' )
		->assertSet( 'sidebarOpen', true );
} );

test( 'editor can set sidebar tab', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'sidebarTab', 'blocks' )
		->call( 'setSidebarTab', 'sections' )
		->assertSet( 'sidebarTab', 'sections' )
		->call( 'setSidebarTab', 'layers' )
		->assertSet( 'sidebarTab', 'layers' );
} );

test( 'editor can save content', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'save' )
		->assertSet( 'saveStatus', 'saved' )
		->assertSet( 'isDirty', false );
} );

test( 'editor can deselect active block', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'deselectBlock' )
		->assertSet( 'activeBlockId', null );
} );

test( 'editor marks dirty on sections update', function (): void {
	$newSections = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'sections-updated', sections: $newSections )
		->assertSet( 'isDirty', true )
		->assertSet( 'saveStatus', 'unsaved' );
} );

// =========================================
// Save/Publish Workflow Tests
// =========================================

test( 'editor save creates a manual revision', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'save' );

	$revision = ArtisanPackUI\VisualEditor\Models\ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'manual' )
		->first();

	expect( $revision )->not->toBeNull();
} );

test( 'editor save updates lastSaved', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'save' )
		->assertSet( 'lastSaved', now()->format( 'g:i A' ) );
} );

test( 'editor publish handler opens pre-publish panel', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'showPrePublishPanel', false )
		->call( 'publish' )
		->assertSet( 'showPrePublishPanel', true )
		->assertNotSet( 'prePublishChecks', [] );
} );

test( 'editor confirmPublish changes content status to published', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'confirmPublish' );

	$this->content->refresh();

	expect( $this->content->status )->toBe( 'published' )
		->and( $this->content->published_at )->not->toBeNull();
} );

test( 'editor unpublish changes content status to draft', function (): void {
	$this->content->update( [
		'status'       => 'published',
		'published_at' => now(),
	] );

	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'unpublish' );

	$this->content->refresh();

	expect( $this->content->status )->toBe( 'draft' );
} );

test( 'editor autosave creates revision when dirty', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'isDirty', true )
		->call( 'autosave' );

	$revision = ArtisanPackUI\VisualEditor\Models\ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'autosave' )
		->first();

	expect( $revision )->not->toBeNull();
} );

test( 'editor autosave skips when not dirty', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'isDirty', false )
		->call( 'autosave' );

	$revision = ArtisanPackUI\VisualEditor\Models\ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'autosave' )
		->first();

	expect( $revision )->toBeNull();
} );

test( 'editor closePrePublishPanel resets panel state', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showPrePublishPanel', true )
		->set( 'scheduleDate', '2027-01-01' )
		->set( 'scheduleTime', '10:00' )
		->call( 'closePrePublishPanel' )
		->assertSet( 'showPrePublishPanel', false )
		->assertSet( 'scheduleDate', '' )
		->assertSet( 'scheduleTime', '' );
} );

test( 'editor submitForReview changes status to pending', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'submitForReview' );

	$this->content->refresh();

	expect( $this->content->status )->toBe( 'pending' );
} );
