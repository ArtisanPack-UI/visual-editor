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
