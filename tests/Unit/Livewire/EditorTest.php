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
		'blocks'    => [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ],
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
		->assertSet( 'activeBlockId', null )
		->assertSet( 'showSettingsDrawer', false )
		->assertSet( 'settingsDrawerTab', 'styles' )
		->assertSet( 'contentTitle', 'Test Page' )
		->assertSet( 'contentSlug', $this->content->slug )
		->assertSet( 'contentExcerpt', '' )
		->assertSet( 'contentMetaTitle', '' )
		->assertSet( 'contentMetaDescription', '' );
} );

test( 'editor loads blocks from content', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'blocks', $this->content->blocks );
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

test( 'editor marks dirty on blocks update', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
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

	$now = now();
	Illuminate\Support\Carbon::setTestNow( $now );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'save' )
		->assertSet( 'lastSaved', $now->format( 'g:i A' ) );

	Illuminate\Support\Carbon::setTestNow();
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

// =========================================
// Settings Drawer Tests
// =========================================

test( 'editor can toggle settings drawer', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'showSettingsDrawer', false )
		->call( 'toggleSettingsDrawer' )
		->assertSet( 'showSettingsDrawer', true )
		->call( 'toggleSettingsDrawer' )
		->assertSet( 'showSettingsDrawer', false );
} );

test( 'editor can set settings drawer tab', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'settingsDrawerTab', 'styles' )
		->call( 'setSettingsDrawerTab', 'settings' )
		->assertSet( 'settingsDrawerTab', 'settings' )
		->call( 'setSettingsDrawerTab', 'page' )
		->assertSet( 'settingsDrawerTab', 'page' )
		->call( 'setSettingsDrawerTab', 'styles' )
		->assertSet( 'settingsDrawerTab', 'styles' );
} );

test( 'editor opens settings drawer on block selection', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'showSettingsDrawer', false )
		->dispatch( 'block-selected', blockId: 've-1' )
		->assertSet( 'showSettingsDrawer', true )
		->assertSet( 'activeBlockId', 've-1' )
		->assertSet( 'settingsDrawerTab', 'styles' );
} );

test( 'editor switches to page tab on block deselection', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->set( 'settingsDrawerTab', 'styles' )
		->call( 'deselectBlock' )
		->assertSet( 'activeBlockId', null )
		->assertSet( 'settingsDrawerTab', 'page' );
} );

test( 'editor closes settings drawer when opening pre-publish panel', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->call( 'publish' )
		->assertSet( 'showSettingsDrawer', false )
		->assertSet( 'showPrePublishPanel', true );
} );

test( 'editor closes pre-publish panel when opening settings drawer', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showPrePublishPanel', true )
		->call( 'toggleSettingsDrawer' )
		->assertSet( 'showSettingsDrawer', true )
		->assertSet( 'showPrePublishPanel', false );
} );

test( 'editor handles toggle settings event from toolbar', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'showSettingsDrawer', false )
		->dispatch( 'editor-toggle-settings' )
		->assertSet( 'showSettingsDrawer', true );
} );

test( 'editor shows block settings form for selected block', function (): void {
	$content = Content::create( [
		'title'     => 'Image Page',
		'slug'      => 'image-page',
		'blocks'    => [ [ 'id' => 've-img1', 'type' => 'image', 'data' => [], 'settings' => [] ] ],
		'settings'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	Livewire::test( 'visual-editor::editor', [ 'content' => $content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'settings' )
		->set( 'activeBlockId', 've-img1' )
		->assertSee( 'Drop Shadow' );
} );

test( 'editor shows no settings message for block without settings schema', function (): void {
	$content = Content::create( [
		'title'     => 'List Page',
		'slug'      => 'list-page',
		'blocks'    => [ [ 'id' => 've-10', 'type' => 'list', 'data' => [], 'settings' => [] ] ],
		'settings'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	Livewire::test( 'visual-editor::editor', [ 'content' => $content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'settings' )
		->set( 'activeBlockId', 've-10' )
		->assertSee( 'This block has no configurable settings.' );
} );

test( 'editor shows no block selected message when no block active', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'settings' )
		->set( 'activeBlockId', null )
		->assertSee( 'Select a block on the canvas to view its settings.' );
} );

test( 'editor shows page settings form on page tab', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'page' )
		->assertSee( 'Title' )
		->assertSee( 'Slug' )
		->assertSee( 'Excerpt' )
		->assertSee( 'SEO' )
		->assertSee( 'Meta Title' )
		->assertSee( 'Meta Description' );
} );

// =========================================
// Block Settings Tests
// =========================================

test( 'editor updateBlockSetting updates block settings', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'alignment', 'center' )
		->assertSet( 'blocks.0.settings.alignment', 'center' );
} );

test( 'editor updateBlockSetting marks editor dirty', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'color', '#ff0000' )
		->assertSet( 'isDirty', true )
		->assertSet( 'saveStatus', 'unsaved' );
} );

test( 'editor updateBlockSetting does nothing without active block', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'activeBlockId', null )
		->call( 'updateBlockSetting', 'alignment', 'center' )
		->assertSet( 'isDirty', false );
} );

test( 'editor getActiveBlockConfig returns config for active block', function (): void {
	$component = Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' );

	$config = $component->call( 'getActiveBlockConfig' )->get( 'getActiveBlockConfig' );

	// The heading block has an anchor setting in its settings_schema.
	$component->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'settings' )
		->assertSee( 'HTML Anchor' );
} );

// =========================================
// Page Settings Tests
// =========================================

test( 'editor initializes page settings from content', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'contentTitle', 'Test Page' )
		->assertSet( 'contentSlug', $this->content->slug );
} );

test( 'editor save includes page settings', function (): void {
	$this->actingAs( $this->user );

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'contentTitle', 'Updated Page Title' )
		->set( 'contentMetaTitle', 'Updated Meta' )
		->call( 'save' );

	$this->content->refresh();

	expect( $this->content->title )->toBe( 'Updated Page Title' )
		->and( $this->content->meta_title )->toBe( 'Updated Meta' );
} );

test( 'editor marks dirty when content title changes', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'isDirty', false )
		->set( 'contentTitle', 'New Title' )
		->assertSet( 'isDirty', true )
		->assertSet( 'saveStatus', 'unsaved' );
} );

test( 'editor marks dirty when content slug changes', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'isDirty', false )
		->set( 'contentSlug', 'new-slug' )
		->assertSet( 'isDirty', true )
		->assertSet( 'saveStatus', 'unsaved' );
} );

// =========================================
// Sidebar Toggle Event Tests
// =========================================

test( 'editor handles toggle sidebar event', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'sidebarOpen', true )
		->dispatch( 'editor-toggle-sidebar' )
		->assertSet( 'sidebarOpen', false )
		->dispatch( 'editor-toggle-sidebar' )
		->assertSet( 'sidebarOpen', true );
} );

test( 'editor settings panel renders inline', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->assertSeeHtml( 've-settings-panel' );
} );

// =========================================
// Styles Tab Tests
// =========================================

test( 'editor styles tab shows sizing section for heading block', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'styles' )
		->set( 'activeBlockId', 've-1' )
		->assertSee( 'Sizing' )
		->assertSee( 'Padding' )
		->assertSee( 'Margin' );
} );

test( 'editor styles tab shows typography section for heading block', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'styles' )
		->set( 'activeBlockId', 've-1' )
		->assertSee( 'Typography' )
		->assertSee( 'Font Family' );
} );

test( 'editor styles tab shows colors section for heading block', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'styles' )
		->set( 'activeBlockId', 've-1' )
		->assertSee( 'Colors' )
		->assertSee( 'Text Color' )
		->assertSee( 'Background Color' );
} );

test( 'editor styles tab shows screen size selector', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'styles' )
		->set( 'activeBlockId', 've-1' )
		->assertSee( 'Screen Size' );
} );

test( 'editor styles tab shows state selector', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'styles' )
		->set( 'activeBlockId', 've-1' )
		->assertSee( 'State' );
} );

test( 'editor styles tab shows no styles message when no block selected', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->set( 'settingsDrawerTab', 'styles' )
		->set( 'activeBlockId', null )
		->assertSee( 'Select a block on the canvas to view its styles.' );
} );

test( 'editor settings panel has three tabs', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->assertSee( 'Styles' )
		->assertSee( 'Settings' )
		->assertSee( 'Page' );
} );

test( 'editor settings panel has Edit Block header', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'showSettingsDrawer', true )
		->assertSee( 'Edit Block' );
} );

// =========================================
// Dot-Notation Block Settings Tests
// =========================================

test( 'editor updateBlockSetting supports dot-notation keys', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'styles.base.default.sizing.padding_top', '16' )
		->assertSet( 'blocks.0.settings.styles.base.default.sizing.padding_top', '16' );
} );

test( 'editor updateBlockSetting stores nested style values correctly', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'styles.base.default.colors.text_color', '#ff0000' )
		->assertSet( 'blocks.0.settings.styles.base.default.colors.text_color', '#ff0000' );
} );

test( 'editor updateBlockSetting stores multiple breakpoint styles independently', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'styles.base.default.sizing.padding_top', '8' )
		->call( 'updateBlockSetting', 'styles.md.default.sizing.padding_top', '16' )
		->call( 'updateBlockSetting', 'styles.lg.default.sizing.padding_top', '24' )
		->assertSet( 'blocks.0.settings.styles.base.default.sizing.padding_top', '8' )
		->assertSet( 'blocks.0.settings.styles.md.default.sizing.padding_top', '16' )
		->assertSet( 'blocks.0.settings.styles.lg.default.sizing.padding_top', '24' );
} );

test( 'editor updateBlockSetting stores state-specific styles independently', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'styles.base.default.colors.text_color', '#000000' )
		->call( 'updateBlockSetting', 'styles.base.hover.colors.text_color', '#0066cc' )
		->assertSet( 'blocks.0.settings.styles.base.default.colors.text_color', '#000000' )
		->assertSet( 'blocks.0.settings.styles.base.hover.colors.text_color', '#0066cc' );
} );

// =========================================
// Undo/Redo Tests
// =========================================

test( 'editor undo stack starts empty', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'undoStack', [] );
} );

test( 'editor redo stack starts empty', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->assertSet( 'redoStack', [] );
} );

test( 'editor pushHistory adds to undo stack', function (): void {
	$initialBlocks = [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'pushHistory', $initialBlocks )
		->assertSet( 'undoStack', [ $initialBlocks ] );
} );

test( 'editor pushHistory clears redo stack', function (): void {
	$blocks = [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'redoStack', [ $blocks ] )
		->call( 'pushHistory', $blocks )
		->assertSet( 'redoStack', [] );
} );

test( 'editor pushHistory caps at max_history_states', function (): void {
	config()->set( 'artisanpack.visual-editor.editor.max_history_states', 3 );

	$component = Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] );

	for ( $i = 1; $i <= 5; $i++ ) {
		$component->call( 'pushHistory', [ [ 'id' => "ve-{$i}", 'type' => 'text' ] ] );
	}

	$stack = $component->get( 'undoStack' );
	expect( $stack )->toHaveCount( 3 )
		->and( $stack[0][0]['id'] )->toBe( 've-3' )
		->and( $stack[2][0]['id'] )->toBe( 've-5' );
} );

test( 'editor pushHistory dispatches undo-redo-state-changed', function (): void {
	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'pushHistory', [ [ 'id' => 've-1', 'type' => 'heading' ] ] )
		->assertDispatched( 'undo-redo-state-changed', canUndo: true, canRedo: false );
} );

test( 'editor undo restores previous block state', function (): void {
	$originalBlocks = [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ];
	$newBlocks      = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->assertSet( 'blocks', $newBlocks )
		->call( 'undo' )
		->assertSet( 'blocks', $originalBlocks );
} );

test( 'editor undo pushes current state to redo stack', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->assertSet( 'redoStack', [ $newBlocks ] );
} );

test( 'editor undo does nothing when stack is empty', function (): void {
	$originalBlocks = $this->content->blocks;

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'undo' )
		->assertSet( 'blocks', $originalBlocks )
		->assertSet( 'redoStack', [] );
} );

test( 'editor undo dispatches canvas-sync-blocks', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->assertDispatched( 'canvas-sync-blocks' );
} );

test( 'editor undo dispatches undo-redo-state-changed', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->assertDispatched( 'undo-redo-state-changed', canUndo: false, canRedo: true );
} );

test( 'editor redo restores next block state', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->call( 'redo' )
		->assertSet( 'blocks', $newBlocks );
} );

test( 'editor redo pushes current state to undo stack', function (): void {
	$originalBlocks = $this->content->blocks;
	$newBlocks      = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	$component = Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->call( 'redo' );

	$undoStack = $component->get( 'undoStack' );

	// After blocks-updated → undo → redo, the undo stack should have the original blocks.
	expect( $undoStack )->toHaveCount( 1 )
		->and( $undoStack[0] )->toBe( $originalBlocks );
} );

test( 'editor redo does nothing when stack is empty', function (): void {
	$originalBlocks = $this->content->blocks;

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->call( 'redo' )
		->assertSet( 'blocks', $originalBlocks )
		->assertSet( 'undoStack', [] );
} );

test( 'editor redo dispatches canvas-sync-blocks', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->call( 'redo' )
		->assertDispatched( 'canvas-sync-blocks' );
} );

test( 'editor redo dispatches undo-redo-state-changed', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->call( 'undo' )
		->call( 'redo' )
		->assertDispatched( 'undo-redo-state-changed', canUndo: true, canRedo: false );
} );

test( 'editor onBlocksUpdated pushes history', function (): void {
	$originalBlocks = $this->content->blocks;
	$newBlocks      = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->assertSet( 'undoStack', [ $originalBlocks ] );
} );

test( 'editor onLayersReordered pushes history', function (): void {
	$originalBlocks  = $this->content->blocks;
	$reorderedBlocks = [
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'layers-reordered', blocks: $reorderedBlocks )
		->assertSet( 'undoStack', [ $originalBlocks ] );
} );

test( 'editor updateBlockSetting pushes history', function (): void {
	$originalBlocks = $this->content->blocks;

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->set( 'activeBlockId', 've-1' )
		->call( 'updateBlockSetting', 'alignment', 'center' )
		->assertSet( 'undoStack', [ $originalBlocks ] );
} );

test( 'editor undo marks editor as dirty', function (): void {
	$newBlocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::editor', [ 'content' => $this->content ] )
		->dispatch( 'blocks-updated', blocks: $newBlocks )
		->set( 'isDirty', false )
		->set( 'saveStatus', 'saved' )
		->call( 'undo' )
		->assertSet( 'isDirty', true )
		->assertSet( 'saveStatus', 'unsaved' );
} );
