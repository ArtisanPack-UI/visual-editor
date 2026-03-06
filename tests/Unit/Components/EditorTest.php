<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\View\Components\Editor;

test( 'editor can be instantiated with defaults', function (): void {
	// Mock the block registry to avoid triggering Blade icon rendering
	// in custom toolbar/inspector panels.
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->initialBlocks )->toBe( [] );
	expect( $component->patterns )->toBe( [] );
	expect( $component->blockTransforms )->toBe( [] );
	expect( $component->blockVariations )->toBe( [] );
	expect( $component->autosave )->toBeFalse();
	expect( $component->autosaveInterval )->toBe( 60 );
	expect( $component->documentStatus )->toBe( 'draft' );
	expect( $component->showSidebar )->toBeTrue();
	expect( $component->mode )->toBe( 'visual' );
} );

test( 'editor accepts custom props', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor(
		id: 'my-editor',
		autosave: false,
		autosaveInterval: 120,
		documentStatus: 'published',
		showSidebar: false,
		mode: 'code',
	);

	expect( $component->uuid )->toContain( 'my-editor' );
	expect( $component->autosave )->toBeFalse();
	expect( $component->autosaveInterval )->toBe( 120 );
	expect( $component->documentStatus )->toBe( 'published' );
	expect( $component->showSidebar )->toBeFalse();
	expect( $component->mode )->toBe( 'code' );
} );

test( 'editor builds empty data when registry is empty', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor();

	expect( $component->inserterBlocks )->toBeArray()->toBeEmpty();
	expect( $component->renderedBlocks )->toBeArray()->toBeEmpty();
	expect( $component->defaultBlockTemplates )->toBeArray()->toBeEmpty();
	expect( $component->blockMetadata )->toBeArray()->toBeEmpty();
	expect( $component->inspectorBlockNames )->toBeArray()->toBeEmpty();
	expect( $component->inspectorBlockDescriptions )->toBeArray()->toBeEmpty();
	expect( $component->inspectorBlockTypes )->toBeArray()->toBeEmpty();
	expect( $component->toolbarBlockIcons )->toBeArray()->toBeEmpty();
	expect( $component->transformableBlocks )->toBeArray()->toBeEmpty();
	expect( $component->blockAlignSupports )->toBeArray()->toBeEmpty();
	expect( $component->customToolbarHtml )->toBeArray()->toBeEmpty();
	expect( $component->customInspectorHtml )->toBeArray()->toBeEmpty();
} );

test( 'editor builds editor shortcuts', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor();

	expect( $component->editorShortcuts )->toBeArray();
	expect( count( $component->editorShortcuts ) )->toBeGreaterThan( 0 );

	$first = $component->editorShortcuts[0];
	expect( $first )->toHaveKeys( [ 'name', 'keys', 'description', 'category' ] );
} );

test( 'editor icon renderer uses default when none provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor();

	expect( $component->iconRenderer )->toBeCallable();

	$result = ( $component->iconRenderer )( 'paragraph' );
	expect( $result )->toBeString();
	expect( $result )->toContain( 'svg' );
} );

test( 'editor icon renderer uses custom when provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$custom = function ( string $icon ): string {
		return '<i class="icon-' . $icon . '"></i>';
	};

	$component = new Editor( customIconRenderer: $custom );

	expect( $component->iconRenderer )->toBe( $custom );
	expect( ( $component->iconRenderer )( 'paragraph' ) )->toBe( '<i class="icon-paragraph"></i>' );
} );

test( 'editor default icon renderer returns empty string for unknown icon', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor();
	$result    = ( $component->iconRenderer )( 'nonexistent-icon-xyz' );

	// Unknown icons fall back to the default 'block' icon (cube SVG).
	expect( $result )->toBeString();
	expect( $result )->toContain( 'svg' );
} );

test( 'editor builds rendered blocks from initial blocks', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$blocks = [
		[
			'id'         => 'block-1',
			'type'       => 'paragraph',
			'attributes' => [ 'text' => 'Hello world' ],
		],
	];

	$component = new Editor( initialBlocks: $blocks );

	expect( $component->renderedBlocks )->toBeArray();
} );

test( 'editor builds patterns with previews', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$patterns = [
		[
			'name'   => 'test-pattern',
			'title'  => 'Test Pattern',
			'blocks' => [
				[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Hello' ] ],
			],
		],
	];

	$component = new Editor( patterns: $patterns );

	expect( $component->patternsWithPreviews )->toBeArray();
	expect( $component->patternsWithPreviews )->toHaveCount( 1 );
	expect( $component->patternsWithPreviews[0] )->toHaveKey( 'preview' );
} );

test( 'editor renders view name', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new Editor();
	$view      = $component->render();

	expect( $view->name() )->toBe( 'visual-editor::components.editor' );
} );
