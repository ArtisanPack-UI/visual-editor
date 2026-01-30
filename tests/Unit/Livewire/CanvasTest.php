<?php

declare( strict_types=1 );

use Livewire\Livewire;

// --- Rendering ---

test( 'canvas component renders successfully', function (): void {
	Livewire::test( 'visual-editor::canvas' )
		->assertStatus( 200 );
} );

test( 'canvas does not show empty state when no blocks', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'blocks' => [] ] )
		->assertDontSee( 'Start building your page' );
} );

test( 'canvas renders blocks when provided', function (): void {
	$blocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSee( 'Type heading...' )
		->assertSee( 'Type text...' );
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

// --- Rich Text Editing ---

test( 'canvas saves inline edit with HTML content', function (): void {
	$blocks = [
		[ 'id' => 've-rt1', 'type' => 'text', 'content' => [ 'text' => 'Hello' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-rt1' )
		->call( 'saveInlineEdit', 've-rt1', '<strong>Bold text</strong>' )
		->assertSet( 'editingBlockId', null )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['text'] )->toBe( '<strong>Bold text</strong>' );
} );

test( 'canvas renders block content as HTML for richtext blocks', function (): void {
	$blocks = [
		[ 'id' => 've-rt2', 'type' => 'text', 'content' => [ 'text' => 'Some rich content' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSee( 'Some rich content' );
} );

test( 'canvas shows global toolbar for any selected block', function (): void {
	$blocks = [
		[ 'id' => 've-rt3', 'type' => 'text', 'content' => [ 'text' => 'Editable' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-rt3' )
		->assertSeeHtml( 've-global-toolbar' );
} );

test( 'canvas shows global toolbar for heading blocks', function (): void {
	$blocks = [
		[ 'id' => 've-pt1', 'type' => 'heading', 'content' => [ 'text' => 'Heading' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-pt1' )
		->assertSeeHtml( 've-global-toolbar' );
} );

test( 'canvas does not show global toolbar for unselected blocks', function (): void {
	$blocks = [
		[ 'id' => 've-pt2', 'type' => 'text', 'content' => [ 'text' => 'Content' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertDontSeeHtml( 've-global-toolbar' );
} );

test( 'canvas shows empty state placeholder for blocks without content', function (): void {
	$blocks = [
		[ 'id' => 've-empty1', 'type' => 'text', 'content' => [ 'text' => '' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSee( 'Type text...' );
} );

// --- Save and Navigate ---

test( 'canvas saves content and navigates to next block', function (): void {
	$blocks = [
		[ 'id' => 've-sn1', 'type' => 'heading', 'content' => [ 'text' => 'Original' ], 'settings' => [] ],
		[ 'id' => 've-sn2', 'type' => 'text', 'content' => [ 'text' => '' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-sn1' )
		->call( 'saveAndNavigate', 've-sn1', 'Updated heading', 'down' )
		->assertSet( 'activeBlockId', 've-sn2' )
		->assertSet( 'editingBlockId', 've-sn2' )
		->assertDispatched( 'blocks-updated' );
} );

test( 'canvas saves content and navigates to previous block', function (): void {
	$blocks = [
		[ 'id' => 've-sn1', 'type' => 'heading', 'content' => [ 'text' => '' ], 'settings' => [] ],
		[ 'id' => 've-sn2', 'type' => 'text', 'content' => [ 'text' => 'Original' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-sn2' )
		->call( 'saveAndNavigate', 've-sn2', 'Updated text', 'up' )
		->assertSet( 'activeBlockId', 've-sn1' )
		->assertSet( 'editingBlockId', 've-sn1' )
		->assertDispatched( 'blocks-updated' );
} );

test( 'canvas saveAndNavigate stays on same block at top boundary', function (): void {
	$blocks = [
		[ 'id' => 've-sn1', 'type' => 'heading', 'content' => [ 'text' => '' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-sn1' )
		->call( 'saveAndNavigate', 've-sn1', 'Content', 'up' )
		->assertSet( 'activeBlockId', 've-sn1' )
		->assertSet( 'editingBlockId', 've-sn1' );
} );

test( 'canvas saveAndNavigate exits to typing area at bottom boundary', function (): void {
	$blocks = [
		[ 'id' => 've-sn1', 'type' => 'heading', 'content' => [ 'text' => '' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-sn1' )
		->call( 'saveAndNavigate', 've-sn1', 'Saved Content', 'down' )
		->assertSet( 'activeBlockId', null )
		->assertSet( 'editingBlockId', null )
		->assertDispatched( 'focus-typing-area' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['text'] )->toBe( 'Saved Content' );
} );

test( 'canvas saveAndNavigate selects non-editable block without editing', function (): void {
	$blocks = [
		[ 'id' => 've-sn1', 'type' => 'text', 'content' => [ 'text' => '' ], 'settings' => [] ],
		[ 'id' => 've-sn2', 'type' => 'divider', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-sn1' )
		->call( 'saveAndNavigate', 've-sn1', 'Some text', 'down' )
		->assertSet( 'activeBlockId', 've-sn2' )
		->assertSet( 'editingBlockId', null );
} );

test( 'canvas navigateBlocks enters edit mode on editable target', function (): void {
	$blocks = [
		[ 'id' => 've-nb1', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-nb2', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-nb1' )
		->dispatch( 'canvas-navigate', direction: 'down' )
		->assertSet( 'activeBlockId', 've-nb2' )
		->assertSet( 'editingBlockId', 've-nb2' );
} );

test( 'canvas navigateBlocks does not enter edit mode on non-editable target', function (): void {
	$blocks = [
		[ 'id' => 've-nb1', 'type' => 'text', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-nb2', 'type' => 'divider', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'selectBlock', 've-nb1' )
		->dispatch( 'canvas-navigate', direction: 'down' )
		->assertSet( 'activeBlockId', 've-nb2' )
		->assertSet( 'editingBlockId', null );
} );

// --- Insert Block After (Enter Key) ---

test( 'canvas insertBlockAfter creates block after current', function (): void {
	$blocks = [
		[ 'id' => 've-iba1', 'type' => 'heading', 'content' => [ 'text' => 'Title' ], 'settings' => [] ],
		[ 'id' => 've-iba2', 'type' => 'text', 'content' => [ 'text' => 'Paragraph' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'insertBlockAfter', 've-iba1', 'Updated Title' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks )->toHaveCount( 3 )
		->and( $updatedBlocks[0]['id'] )->toBe( 've-iba1' )
		->and( $updatedBlocks[1]['type'] )->toBe( 'text' )
		->and( $updatedBlocks[2]['id'] )->toBe( 've-iba2' );
} );

test( 'canvas insertBlockAfter saves current block content', function (): void {
	$blocks = [
		[ 'id' => 've-iba3', 'type' => 'heading', 'content' => [ 'text' => 'Original' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'insertBlockAfter', 've-iba3', 'Updated Content' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['text'] )->toBe( 'Updated Content' );
} );

test( 'canvas insertBlockAfter enters edit mode on new block', function (): void {
	$blocks = [
		[ 'id' => 've-iba4', 'type' => 'heading', 'content' => [ 'text' => 'Title' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'insertBlockAfter', 've-iba4', 'Title' );

	$updatedBlocks = $component->get( 'blocks' );
	$newBlockId    = $updatedBlocks[1]['id'];

	$component->assertSet( 'activeBlockId', $newBlockId )
		->assertSet( 'editingBlockId', $newBlockId );
} );

test( 'canvas insertBlockAfter dispatches focus event', function (): void {
	$blocks = [
		[ 'id' => 've-iba5', 'type' => 'heading', 'content' => [ 'text' => 'Title' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'insertBlockAfter', 've-iba5', 'Title' )
		->assertDispatched( 'focus-block' )
		->assertDispatched( 'blocks-updated' );
} );

// --- WYSIWYG Rendering ---

test( 'canvas WYSIWYG heading renders with heading tag', function (): void {
	$blocks = [
		[ 'id' => 've-wh1', 'type' => 'heading', 'content' => [ 'text' => 'My Heading', 'level' => 'h2' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSeeHtml( '<h2' )
		->assertSee( 'My Heading' );
} );

test( 'canvas WYSIWYG text block renders with prose', function (): void {
	$blocks = [
		[ 'id' => 've-wt1', 'type' => 'text', 'content' => [ 'text' => 'Some paragraph text' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSeeHtml( 'prose' )
		->assertSee( 'Some paragraph text' );
} );

test( 'canvas WYSIWYG divider renders as hr', function (): void {
	$blocks = [
		[ 'id' => 've-wd1', 'type' => 'divider', 'content' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSeeHtml( '<hr' );
} );

test( 'canvas WYSIWYG quote renders as blockquote', function (): void {
	$blocks = [
		[ 'id' => 've-wq1', 'type' => 'quote', 'content' => [ 'text' => 'Famous quote' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->assertSeeHtml( '<blockquote' )
		->assertSee( 'Famous quote' );
} );

// --- Move Block Up ---

test( 'canvas moves block up by one position', function (): void {
	$blocks = [
		[ 'id' => 've-mu1', 'type' => 'heading', 'content' => [ 'text' => 'First' ], 'settings' => [] ],
		[ 'id' => 've-mu2', 'type' => 'text', 'content' => [ 'text' => 'Second' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'moveBlockUp', 've-mu2' )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['id'] )->toBe( 've-mu2' )
		->and( $updatedBlocks[1]['id'] )->toBe( 've-mu1' );
} );

test( 'canvas moveBlockUp does nothing for first block', function (): void {
	$blocks = [
		[ 'id' => 've-mu3', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-mu4', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'moveBlockUp', 've-mu3' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['id'] )->toBe( 've-mu3' )
		->and( $updatedBlocks[1]['id'] )->toBe( 've-mu4' );
} );

// --- Move Block Down ---

test( 'canvas moves block down by one position', function (): void {
	$blocks = [
		[ 'id' => 've-md1', 'type' => 'heading', 'content' => [ 'text' => 'First' ], 'settings' => [] ],
		[ 'id' => 've-md2', 'type' => 'text', 'content' => [ 'text' => 'Second' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'moveBlockDown', 've-md1' )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['id'] )->toBe( 've-md2' )
		->and( $updatedBlocks[1]['id'] )->toBe( 've-md1' );
} );

test( 'canvas moveBlockDown does nothing for last block', function (): void {
	$blocks = [
		[ 'id' => 've-md3', 'type' => 'heading', 'content' => [], 'settings' => [] ],
		[ 'id' => 've-md4', 'type' => 'text', 'content' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'moveBlockDown', 've-md4' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['id'] )->toBe( 've-md3' )
		->and( $updatedBlocks[1]['id'] )->toBe( 've-md4' );
} );

// --- Change Heading Level ---

test( 'canvas changes heading level', function (): void {
	$blocks = [
		[ 'id' => 've-hl1', 'type' => 'heading', 'content' => [ 'text' => 'Title', 'level' => 'h2' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'changeHeadingLevel', 've-hl1', 'h3' )
		->assertDispatched( 'blocks-updated' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['level'] )->toBe( 'h3' );
} );

test( 'canvas changeHeadingLevel rejects invalid levels', function (): void {
	$blocks = [
		[ 'id' => 've-hl2', 'type' => 'heading', 'content' => [ 'text' => 'Title', 'level' => 'h2' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'changeHeadingLevel', 've-hl2', 'h7' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['level'] )->toBe( 'h2' );
} );

test( 'canvas changeHeadingLevel supports all valid levels', function (): void {
	$blocks = [
		[ 'id' => 've-hl3', 'type' => 'heading', 'content' => [ 'text' => 'Title', 'level' => 'h1' ], 'settings' => [] ],
	];

	foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $level ) {
		$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
			->call( 'changeHeadingLevel', 've-hl3', $level );

		$updatedBlocks = $component->get( 'blocks' );
		expect( $updatedBlocks[0]['content']['level'] )->toBe( $level );
	}
} );

// --- Inline Edit Content Preservation ---

test( 'canvas preserves rich text content in HTML when entering inline edit mode', function (): void {
	$blocks = [
		[ 'id' => 've-cp1', 'type' => 'text', 'content' => [ 'text' => 'Important content' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-cp1' )
		->assertSee( 'Important content' );
} );

test( 'canvas preserves heading content in HTML when entering inline edit mode', function (): void {
	$blocks = [
		[ 'id' => 've-cp2', 'type' => 'heading', 'content' => [ 'text' => 'My Heading', 'level' => 'h2' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-cp2' )
		->assertSee( 'My Heading' );
} );

test( 'canvas preserves plain text content in HTML when entering inline edit mode', function (): void {
	$blocks = [
		[ 'id' => 've-cp3', 'type' => 'quote', 'content' => [ 'text' => 'A famous quote' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-cp3' )
		->assertSee( 'A famous quote' );
} );

test( 'canvas preserves rich text HTML tags when entering inline edit mode', function (): void {
	$blocks = [
		[ 'id' => 've-cp4', 'type' => 'text', 'content' => [ 'text' => '<strong>Bold</strong> and <em>italic</em>' ], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-cp4' )
		->assertSeeHtml( '<strong>Bold</strong>' )
		->assertSeeHtml( '<em>italic</em>' );
} );

test( 'canvas block content survives full inline edit cycle', function (): void {
	$blocks = [
		[ 'id' => 've-cp5', 'type' => 'text', 'content' => [ 'text' => 'Original content' ], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::canvas', [ 'blocks' => $blocks ] )
		->call( 'startInlineEdit', 've-cp5' )
		->call( 'saveInlineEdit', 've-cp5', 'Original content' );

	$updatedBlocks = $component->get( 'blocks' );
	expect( $updatedBlocks[0]['content']['text'] )->toBe( 'Original content' );
} );
