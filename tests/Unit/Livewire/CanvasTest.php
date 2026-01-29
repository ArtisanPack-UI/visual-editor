<?php

declare( strict_types=1 );

use Livewire\Livewire;

// --- Rendering ---

test( 'canvas component renders successfully', function (): void {
	Livewire::test( 'visual-editor::canvas' )
		->assertStatus( 200 );
} );

test( 'canvas shows empty state when no blocks', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->assertSee( 'Start building your page' )
		->assertSee( 'Add blocks from the sidebar to begin creating content.' );
} );

test( 'canvas renders blocks when provided', function (): void {
	$blocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertDontSee( 'Start building your page' )
		->assertSee( 'Heading' )
		->assertSee( 'Text' );
} );

test( 'canvas renders block content', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Hello World' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSee( 'Hello World' );
} );

// --- Block Selection ---

test( 'canvas can select a block', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSet( 'activeBlockId', null )
		->call( 'selectBlock', 've-b1' )
		->assertSet( 'activeBlockId', 've-b1' )
		->assertDispatched( 'block-selected' );
} );

// --- Deselection ---

test( 'canvas deselects all on deselectAll', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-b1' )
		->call( 'deselectAll' )
		->assertSet( 'activeBlockId', null )
		->assertSet( 'editingBlockId', null );
} );

// --- Block Insert ---

test( 'canvas inserts block on block-insert event', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->dispatch( 'block-insert', type: 'heading' )
		->assertNotSet( 'blocks', [] )
		->assertDispatched( 'blocks-updated' );
} );

test( 'canvas inserts block into flat blocks list', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->dispatch( 'block-insert', type: 'heading' )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks )->toHaveCount( 2 );
	expect( $updatedBlocks[1]['type'] )->toBe( 'heading' );
} );

// --- Section Insert ---

test( 'canvas inserts section blocks on section-insert event', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->dispatch( 'section-insert', type: 'text' )
		->assertNotSet( 'blocks', [] )
		->assertDispatched( 'blocks-updated' );
} );

// --- Block Reorder ---

test( 'canvas reorders blocks by ID array', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'reorderBlocks', [ 've-b2', 've-b1' ] )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['id'] )->toBe( 've-b2' );
	expect( $updatedBlocks[1]['id'] )->toBe( 've-b1' );
} );

// --- Delete Block ---

test( 'canvas can delete a block', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'deleteBlock', 've-b1' )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks )->toHaveCount( 1 );
	expect( $updatedBlocks[0]['id'] )->toBe( 've-b2' );
} );

test( 'canvas clears activeBlockId when deleting active block', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-b1' )
		->call( 'deleteBlock', 've-b1' )
		->assertSet( 'activeBlockId', null );
} );

// --- Zoom Controls ---

test( 'canvas can set zoom level', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->assertSet( 'zoomLevel', 100 )
		->call( 'setZoomLevel', 150 )
		->assertSet( 'zoomLevel', 150 );
} );

test( 'canvas clamps zoom level to valid range', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->call( 'setZoomLevel', 30 )
		->assertSet( 'zoomLevel', 50 )
		->call( 'setZoomLevel', 250 )
		->assertSet( 'zoomLevel', 200 );
} );

// --- Grid Toggle ---

test( 'canvas can toggle grid overlay', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->assertSet( 'showGrid', false )
		->call( 'toggleGrid' )
		->assertSet( 'showGrid', true )
		->call( 'toggleGrid' )
		->assertSet( 'showGrid', false );
} );

// --- Inline Editing ---

test( 'canvas can start inline edit', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Hello' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-b1' )
		->assertSet( 'editingBlockId', 've-b1' )
		->assertSet( 'activeBlockId', 've-b1' );
} );

test( 'canvas can save inline edit', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Hello' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-b1' )
		->call( 'saveInlineEdit', 've-b1', 'Updated Text' )
		->assertSet( 'editingBlockId', null )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['text'] )->toBe( 'Updated Text' );
} );

// --- Keyboard Navigation ---

test( 'canvas navigates to first block when none selected going down', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->dispatch( 'canvas-navigate', direction: 'down' )
		->assertSet( 'activeBlockId', 've-b1' );
} );

test( 'canvas navigates to last block when none selected going up', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->dispatch( 'canvas-navigate', direction: 'up' )
		->assertSet( 'activeBlockId', 've-b2' );
} );

test( 'canvas navigates between blocks', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-b1' )
		->dispatch( 'canvas-navigate', direction: 'down' )
		->assertSet( 'activeBlockId', 've-b2' );
} );

test( 'canvas does not navigate past boundaries', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-b1' )
		->dispatch( 'canvas-navigate', direction: 'up' )
		->assertSet( 'activeBlockId', 've-b1' );
} );

// --- Keyboard Delete ---

test( 'canvas deleteSelected removes active block', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-b1' )
		->dispatch( 'canvas-delete-selected' )
		->assertSet( 'activeBlockId', null )
		->assertDispatched( 'blocks-updated' );
} );

test( 'canvas deleteSelected does nothing when nothing is selected', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->dispatch( 'canvas-delete-selected' );

	expect( $component->get( 'blocks' ) )->toHaveCount( 1 );
} );

// --- Insert Block With Content ---

test( 'canvas inserts block with initial text content', function (): void {
	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->call( 'insertBlockWithContent', 'text', 'Hello from typing area' )
		->assertDispatched( 'blocks-updated' );

	$blocks = $component->get( 'blocks' );
	expect( $blocks )->toHaveCount( 1 )
		->and( $blocks[0]['type'] )->toBe( 'text' )
		->and( $blocks[0]['content']['text'] )->toBe( 'Hello from typing area' );
} );

test( 'canvas inserts block without content when empty string provided', function (): void {
	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->call( 'insertBlockWithContent', 'heading', '' )
		->assertDispatched( 'blocks-updated' );

	$blocks = $component->get( 'blocks' );
	expect( $blocks )->toHaveCount( 1 )
		->and( $blocks[0]['type'] )->toBe( 'heading' )
		->and( $blocks[0]['content'] )->toBe( [] );
} );

test( 'canvas appends typed block after existing blocks', function (): void {
	$existingBlocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Title' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $existingBlocks ] )
		->call( 'insertBlockWithContent', 'text', 'New paragraph' )
		->assertDispatched( 'blocks-updated' );

	$blocks = $component->get( 'blocks' );
	expect( $blocks )->toHaveCount( 2 )
		->and( $blocks[0]['id'] )->toBe( 've-b1' )
		->and( $blocks[1]['type'] )->toBe( 'text' )
		->and( $blocks[1]['content']['text'] )->toBe( 'New paragraph' );
} );

// --- Slash Menu Data ---

test( 'canvas exposes slash menu blocks as computed property', function (): void {
	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] );

	$instance    = $component->instance();
	$slashBlocks = $instance->slashMenuBlocks();

	expect( $slashBlocks )->toBeArray()
		->and( $slashBlocks )->not->toBeEmpty();

	$firstCategory = $slashBlocks[0];
	expect( $firstCategory )->toHaveKeys( [ 'key', 'name', 'icon', 'blocks' ] )
		->and( $firstCategory['blocks'] )->toBeArray();

	if ( count( $firstCategory['blocks'] ) > 0 ) {
		$firstBlock = $firstCategory['blocks'][0];
		expect( $firstBlock )->toHaveKeys( [ 'type', 'name', 'icon', 'keywords' ] );
	}
} );

// --- Typing Area Rendering ---

test( 'canvas always renders typing area with blocks', function (): void {
	$blocks = [
		[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSee( 'Type to add a block, or type / for commands...' );
} );

test( 'canvas always renders typing area without blocks', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->assertSee( 'Type to add a block, or type / for commands...' );
} );

test( 'canvas renders slash command menu markup', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->assertSee( 'No matching blocks found' );
} );
