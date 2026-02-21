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
} );

test( 'editor state accepts custom props', function (): void {
	$blocks    = [
		[ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ], 'innerBlocks' => [] ],
	];
	$patterns  = [
		[ 'name' => 'Hero', 'category' => 'header', 'blocks' => [] ],
	];
	$component = new EditorState(
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
