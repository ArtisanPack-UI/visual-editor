<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\EditorState;

test( 'editor state can be instantiated with defaults', function (): void {
	$component = new EditorState();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->initialBlocks )->toBe( [] );
	expect( $component->maxHistorySize )->toBe( 50 );
	expect( $component->mode )->toBe( 'visual' );
	expect( $component->showSidebar )->toBeTrue();
	expect( $component->showInserter )->toBeFalse();
	expect( $component->devicePreview )->toBe( 'desktop' );
	expect( $component->saveStatus )->toBe( 'saved' );
	expect( $component->autosave )->toBeTrue();
	expect( $component->autosaveInterval )->toBe( 60 );
	expect( $component->documentStatus )->toBe( 'draft' );
	expect( $component->scheduledDate )->toBeNull();
	expect( $component->patterns )->toBe( [] );
	expect( $component->blockTransforms )->toBe( [] );
	expect( $component->blockVariations )->toBe( [] );
	expect( $component->defaultBlockType )->toBe( 'paragraph' );
	expect( $component->initialMeta )->toBe( [] );
} );

test( 'editor state accepts custom props', function (): void {
	$blocks    = [
		[ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ], 'innerBlocks' => [] ],
	];
	$patterns  = [
		[ 'name' => 'Hero', 'category' => 'header', 'blocks' => [] ],
	];
	$transforms = [
		'paragraph' => [ 'heading' => [ 'content' => 'content' ] ],
	];
	$variations = [
		'group' => [ [ 'name' => 'row', 'label' => 'Row' ] ],
	];
	$meta = [
		'title'   => 'My Post',
		'excerpt' => 'A short summary',
	];
	$component  = new EditorState(
		id: 'main-editor',
		initialBlocks: $blocks,
		maxHistorySize: 100,
		mode: 'code',
		showSidebar: false,
		showInserter: true,
		devicePreview: 'tablet',
		saveStatus: 'unsaved',
		autosave: false,
		autosaveInterval: 30,
		documentStatus: 'scheduled',
		scheduledDate: '2026-03-01 10:00',
		patterns: $patterns,
		blockTransforms: $transforms,
		blockVariations: $variations,
		defaultBlockType: 'heading',
		initialMeta: $meta,
	);

	expect( $component->uuid )->toContain( 'main-editor' );
	expect( $component->initialBlocks )->toBe( $blocks );
	expect( $component->maxHistorySize )->toBe( 100 );
	expect( $component->mode )->toBe( 'code' );
	expect( $component->showSidebar )->toBeFalse();
	expect( $component->showInserter )->toBeTrue();
	expect( $component->devicePreview )->toBe( 'tablet' );
	expect( $component->saveStatus )->toBe( 'unsaved' );
	expect( $component->autosave )->toBeFalse();
	expect( $component->autosaveInterval )->toBe( 30 );
	expect( $component->documentStatus )->toBe( 'scheduled' );
	expect( $component->scheduledDate )->toBe( '2026-03-01 10:00' );
	expect( $component->patterns )->toBe( $patterns );
	expect( $component->blockTransforms )->toBe( $transforms );
	expect( $component->blockVariations )->toBe( $variations );
	expect( $component->defaultBlockType )->toBe( 'heading' );
	expect( $component->initialMeta )->toBe( $meta );
} );

test( 'editor state falls back to paragraph for empty default block type', function (): void {
	$component = new EditorState( defaultBlockType: '' );

	expect( $component->defaultBlockType )->toBe( 'paragraph' );
} );

test( 'editor state falls back to paragraph for whitespace-only default block type', function (): void {
	$component = new EditorState( defaultBlockType: '   ' );

	expect( $component->defaultBlockType )->toBe( 'paragraph' );
} );

test( 'editor state falls back to visual for invalid mode', function (): void {
	$component = new EditorState( mode: 'invalid' );

	expect( $component->mode )->toBe( 'visual' );
} );

test( 'editor state falls back to desktop for invalid device preview', function (): void {
	$component = new EditorState( devicePreview: 'invalid' );

	expect( $component->devicePreview )->toBe( 'desktop' );
} );

test( 'editor state falls back to saved for invalid save status', function (): void {
	$component = new EditorState( saveStatus: 'invalid' );

	expect( $component->saveStatus )->toBe( 'saved' );
} );

test( 'editor state falls back to draft for invalid document status', function (): void {
	$component = new EditorState( documentStatus: 'invalid' );

	expect( $component->documentStatus )->toBe( 'draft' );
} );

test( 'editor state enforces minimum history size', function (): void {
	$component = new EditorState( maxHistorySize: 0 );

	expect( $component->maxHistorySize )->toBe( 50 );
} );

test( 'editor state enforces minimum autosave interval', function (): void {
	$component = new EditorState( autosaveInterval: 0 );

	expect( $component->autosaveInterval )->toBe( 60 );
} );

test( 'save status map returns uppercase-keyed map', function (): void {
	$map = EditorState::saveStatusMap();

	expect( $map )->toBe( [
		'SAVED'   => 'saved',
		'UNSAVED' => 'unsaved',
		'SAVING'  => 'saving',
		'ERROR'   => 'error',
	] );
} );

test( 'document status map returns uppercase-keyed map', function (): void {
	$map = EditorState::documentStatusMap();

	expect( $map )->toBe( [
		'DRAFT'     => 'draft',
		'PUBLISHED' => 'published',
		'SCHEDULED' => 'scheduled',
		'PENDING'   => 'pending',
	] );
} );

test( 'editor state renders', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );
	expect( $view )->not->toBeNull();
} );

test( 'editor state renders with slot content', function (): void {
	$this->blade( '<x-ve-editor-state>Editor Content</x-ve-editor-state>' )
		->assertSee( 'Editor Content' );
} );

test( 'editor state renders with alpine store initialization', function (): void {
	$this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' )
		->assertSee( "Alpine.store( 'editor'", false );
} );

test( 'editor state renders save status constants as frozen object', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'SAVE_STATUS: Object.freeze(', false );
	$view->assertSee( 'SAVED', false );
	$view->assertSee( 'UNSAVED', false );
	$view->assertSee( 'SAVING', false );
	$view->assertSee( 'ERROR', false );
} );

test( 'editor state renders document status constants as frozen object', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'DOCUMENT_STATUS: Object.freeze(', false );
	$view->assertSee( 'DRAFT', false );
	$view->assertSee( 'PUBLISHED', false );
	$view->assertSee( 'SCHEDULED', false );
	$view->assertSee( 'PENDING', false );
} );

test( 'editor state uses save status constants instead of magic strings', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this.SAVE_STATUS.UNSAVED', false );
	$view->assertSee( 'this.SAVE_STATUS.SAVING', false );
	$view->assertSee( 'this.SAVE_STATUS.SAVED', false );
	$view->assertSee( 'this.SAVE_STATUS.ERROR', false );
} );

test( 'editor state uses document status constants instead of magic strings', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this.DOCUMENT_STATUS.SCHEDULED', false );
} );

test( 'editor state renders save status transition map', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( '_saveTransitions:', false );
	$view->assertSee( '_canTransitionTo(', false );
} );

test( 'editor state transition guards protect markDirty', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'markDirty()', false );
	$view->assertSee( 'this._canTransitionTo( this.SAVE_STATUS.UNSAVED )', false );
} );

test( 'editor state transition guards protect markSaving', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this._canTransitionTo( this.SAVE_STATUS.SAVING )', false );
} );

test( 'editor state transition guards protect markSaved', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this._canTransitionTo( this.SAVE_STATUS.SAVED )', false );
} );

test( 'editor state transition guards protect markError', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this._canTransitionTo( this.SAVE_STATUS.ERROR )', false );
} );

test( 'editor state transition map allows unsaved to unsaved for idempotent markDirty', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( "unsaved: [ 'unsaved', 'saving' ]", false );
} );

test( 'editor state transition map blocks saving to unsaved', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	// saving should only allow transitions to saved or error, not unsaved
	$view->assertSee( "saving: [ 'saved', 'error' ]", false );
} );

test( 'editor state initializes pendingDirty flag', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( '_pendingDirty: false', false );
} );

test( 'editor state markDirty sets pendingDirty when saving', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this.SAVE_STATUS.SAVING === this.saveStatus', false );
	$view->assertSee( 'this._pendingDirty = true', false );
} );

test( 'editor state markSaving clears pendingDirty', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'markSaving()', false );
	$view->assertSee( 'this._pendingDirty = false', false );
} );

test( 'editor state markSaved flushes pendingDirty to unsaved', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'if ( this._pendingDirty )', false );
} );

test( 'editor state renders toggleSidebar method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'toggleSidebar()', false );
	$view->assertSee( 'this.showSidebar = ! this.showSidebar', false );
} );

test( 'editor state renders toggleInserter method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'toggleInserter()', false );
	$view->assertSee( 'this.showInserter = ! this.showInserter', false );
} );

test( 'editor state renders openInserter method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'openInserter()', false );
} );

test( 'editor state renders closeInserter method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'closeInserter()', false );
} );

test( 'editor state renders defaultBlockType in store', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'defaultBlockType:', false );
} );

test( 'editor state renders custom defaultBlockType in store', function (): void {
	$view = $this->blade( '<x-ve-editor-state default-block-type="heading">Content</x-ve-editor-state>' );

	$view->assertSee( "defaultBlockType: 'heading'", false );
} );

test( 'editor state uses defaultBlockType instead of hardcoded paragraph in addBlock', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'type: block.type || this.defaultBlockType', false );
} );

test( 'editor state re-initialization resets pendingDirty', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'store._pendingDirty', false );
} );

test( 'editor state re-initialization updates defaultBlockType', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'store.defaultBlockType', false );
} );

test( 'editor state renders meta object in store', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'meta:', false );
} );

test( 'editor state renders setMeta method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'setMeta( key, value )', false );
} );

test( 'editor state renders getMeta method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'getMeta( key, defaultValue = null )', false );
} );

test( 'editor state renders setMetaBulk method', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'setMetaBulk( data )', false );
} );

test( 'editor state renders setMeta with markDirty and dispatchChange calls', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'this.meta[ key ] = value', false );
} );

test( 'editor state renders meta in dispatchChange event detail', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'meta: JSON.parse( JSON.stringify( this.meta ) )', false );
} );

test( 'editor state renders meta in autosave event detail', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 've-autosave', false );
	$view->assertSee( 'meta: JSON.parse( JSON.stringify( this.meta ) )', false );
} );

test( 'editor state renders meta reset in re-initialization', function (): void {
	$view = $this->blade( '<x-ve-editor-state>Content</x-ve-editor-state>' );

	$view->assertSee( 'store.meta', false );
} );

test( 'editor state renders initial meta values when provided', function (): void {
	$view = $this->blade(
		'<x-ve-editor-state :initial-meta="$meta">Content</x-ve-editor-state>',
		[ 'meta' => [ 'title' => 'Test Title', 'excerpt' => 'Test Excerpt' ] ],
	);

	$view->assertSee( 'Test Title', false );
	$view->assertSee( 'Test Excerpt', false );
} );
