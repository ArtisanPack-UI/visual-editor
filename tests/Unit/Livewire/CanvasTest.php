<?php

declare( strict_types=1 );

use Livewire\Livewire;

// --- Rendering ---

test( 'canvas component renders successfully', function (): void {
	Livewire::test( 'visual-editor::canvas' )
		->assertStatus( 200 );
} );

test( 'canvas shows empty state when no sections', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->assertSee( 'Start building your page' )
		->assertSee( 'Add blocks from the sidebar to begin creating content.' );
} );

test( 'canvas renders sections when provided', function (): void {
	$sections = [
		[ 'id' => 've-1', 'type' => 'heading', 'blocks' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'blocks' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->assertDontSee( 'Start building your page' )
		->assertSee( 'Heading' )
		->assertSee( 'Text' );
} );

test( 'canvas renders blocks within sections', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Hello World' ], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->assertSee( 'Hello World' );
} );

// --- Block Selection ---

test( 'canvas can select a block', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->assertSet( 'activeBlockId', null )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->assertSet( 'activeBlockId', 've-b1' )
		->assertSet( 'selectedSectionId', 've-s1' )
		->assertDispatched( 'block-selected' );
} );

// --- Section Selection ---

test( 'canvas can select a section', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->assertSet( 'selectedSectionId', null )
		->call( 'selectSection', 've-s1' )
		->assertSet( 'selectedSectionId', 've-s1' )
		->assertSet( 'activeBlockId', null )
		->assertDispatched( 'section-selected' );
} );

test( 'canvas selecting section clears active block', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->assertSet( 'activeBlockId', 've-b1' )
		->call( 'selectSection', 've-s1' )
		->assertSet( 'activeBlockId', null )
		->assertSet( 'selectedSectionId', 've-s1' );
} );

// --- Deselection ---

test( 'canvas deselects all on deselectAll', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->call( 'deselectAll' )
		->assertSet( 'activeBlockId', null )
		->assertSet( 'selectedSectionId', null )
		->assertSet( 'editingBlockId', null );
} );

// --- Block Insert ---

test( 'canvas inserts block on block-insert event', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->dispatch( 'block-insert', type: 'heading' )
		->assertNotSet( 'sections', [] )
		->assertDispatched( 'sections-updated' );
} );

test( 'canvas inserts block into selected section', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectSection', 've-s1' )
		->dispatch( 'block-insert', type: 'heading' )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections[0]['blocks'] )->toHaveCount( 1 );
	expect( $updatedSections[0]['blocks'][0]['type'] )->toBe( 'heading' );
} );

// --- Section Insert ---

test( 'canvas inserts section on section-insert event', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->dispatch( 'section-insert', type: 'text' )
		->assertNotSet( 'sections', [] )
		->assertDispatched( 'sections-updated' );
} );

// --- Section Reorder ---

test( 'canvas reorders sections by ID array', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'heading', 'blocks' => [], 'settings' => [] ],
		[ 'id' => 've-s2', 'type' => 'text', 'blocks' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'reorderSections', [ 've-s2', 've-s1' ] )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections[0]['id'] )->toBe( 've-s2' );
	expect( $updatedSections[1]['id'] )->toBe( 've-s1' );
} );

// --- Block Reorder ---

test( 'canvas reorders blocks within a section', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
			[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'reorderBlocks', 've-s1', [ 've-b2', 've-b1' ] )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections[0]['blocks'][0]['id'] )->toBe( 've-b2' );
	expect( $updatedSections[0]['blocks'][1]['id'] )->toBe( 've-b1' );
} );

// --- Move Block Between Sections ---

test( 'canvas moves block between sections', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
		[ 'id' => 've-s2', 'type' => 'text', 'blocks' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'moveBlockBetweenSections', 've-b1', 've-s1', 've-s2', 0 )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections[0]['blocks'] )->toBeEmpty();
	expect( $updatedSections[1]['blocks'] )->toHaveCount( 1 );
	expect( $updatedSections[1]['blocks'][0]['id'] )->toBe( 've-b1' );
} );

// --- Delete Section ---

test( 'canvas can delete a section', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [], 'settings' => [] ],
		[ 'id' => 've-s2', 'type' => 'text', 'blocks' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'deleteSection', 've-s1' )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections )->toHaveCount( 1 );
	expect( $updatedSections[0]['id'] )->toBe( 've-s2' );
} );

test( 'canvas clears selectedSectionId when deleting selected section', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectSection', 've-s1' )
		->call( 'deleteSection', 've-s1' )
		->assertSet( 'selectedSectionId', null );
} );

// --- Delete Block ---

test( 'canvas can delete a block from a section', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
			[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'deleteBlock', 've-b1', 've-s1' )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections[0]['blocks'] )->toHaveCount( 1 );
	expect( $updatedSections[0]['blocks'][0]['id'] )->toBe( 've-b2' );
} );

test( 'canvas clears activeBlockId when deleting active block', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->call( 'deleteBlock', 've-b1', 've-s1' )
		->assertSet( 'activeBlockId', null );
} );

// --- Zoom Controls ---

test( 'canvas can set zoom level', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->assertSet( 'zoomLevel', 100 )
		->call( 'setZoomLevel', 150 )
		->assertSet( 'zoomLevel', 150 );
} );

test( 'canvas clamps zoom level to valid range', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->call( 'setZoomLevel', 30 )
		->assertSet( 'zoomLevel', 50 )
		->call( 'setZoomLevel', 250 )
		->assertSet( 'zoomLevel', 200 );
} );

// --- Grid Toggle ---

test( 'canvas can toggle grid overlay', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->assertSet( 'showGrid', false )
		->call( 'toggleGrid' )
		->assertSet( 'showGrid', true )
		->call( 'toggleGrid' )
		->assertSet( 'showGrid', false );
} );

// --- Inline Editing ---

test( 'canvas can start inline edit', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'text', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Hello' ], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'startInlineEdit', 've-b1' )
		->assertSet( 'editingBlockId', 've-b1' )
		->assertSet( 'activeBlockId', 've-b1' );
} );

test( 'canvas can save inline edit', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'text', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [ 'text' => 'Hello' ], 'settings' => [] ],
		], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'startInlineEdit', 've-b1' )
		->call( 'saveInlineEdit', 've-b1', 've-s1', 'Updated Text' )
		->assertSet( 'editingBlockId', null )
		->assertDispatched( 'sections-updated' );

	$updatedSections = $component->get( 'sections' );
	expect( $updatedSections[0]['blocks'][0]['content']['text'] )->toBe( 'Updated Text' );
} );

// --- Keyboard Navigation ---

test( 'canvas navigates to first block when none selected going down', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->dispatch( 'canvas-navigate', direction: 'down' )
		->assertSet( 'activeBlockId', 've-b1' )
		->assertSet( 'selectedSectionId', 've-s1' );
} );

test( 'canvas navigates to last block when none selected going up', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
		[ 'id' => 've-s2', 'type' => 'text', 'blocks' => [
			[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->dispatch( 'canvas-navigate', direction: 'up' )
		->assertSet( 'activeBlockId', 've-b2' )
		->assertSet( 'selectedSectionId', 've-s2' );
} );

test( 'canvas navigates between blocks across sections', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
		[ 'id' => 've-s2', 'type' => 'text', 'blocks' => [
			[ 'id' => 've-b2', 'type' => 'text', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->dispatch( 'canvas-navigate', direction: 'down' )
		->assertSet( 'activeBlockId', 've-b2' )
		->assertSet( 'selectedSectionId', 've-s2' );
} );

test( 'canvas does not navigate past boundaries', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->dispatch( 'canvas-navigate', direction: 'up' )
		->assertSet( 'activeBlockId', 've-b1' );
} );

// --- Keyboard Delete ---

test( 'canvas deleteSelected removes active block', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [
			[ 'id' => 've-b1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectBlock', 've-b1', 've-s1' )
		->dispatch( 'canvas-delete-selected' )
		->assertSet( 'activeBlockId', null )
		->assertDispatched( 'sections-updated' );
} );

test( 'canvas deleteSelected removes selected section when no block selected', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'selectSection', 've-s1' )
		->dispatch( 'canvas-delete-selected' )
		->assertSet( 'selectedSectionId', null );

	expect( $component->get( 'sections' ) )->toBeEmpty();
} );

test( 'canvas deleteSelected does nothing when nothing is selected', function (): void {
	$sections = [
		[ 'id' => 've-s1', 'type' => 'hero', 'blocks' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->dispatch( 'canvas-delete-selected' );

	expect( $component->get( 'sections' ) )->toHaveCount( 1 );
} );
