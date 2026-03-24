<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\View\Components\TemplatePartEditor;

test( 'template part editor can be instantiated with defaults', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplatePartEditor();

	expect( $component->uuid )->toStartWith( 've-part-editor-' );
	expect( $component->id )->toBeNull();
	expect( $component->initialBlocks )->toBe( [] );
	expect( $component->patterns )->toBe( [] );
	expect( $component->blockTransforms )->toBe( [] );
	expect( $component->blockVariations )->toBe( [] );
	expect( $component->autosave )->toBeFalse();
	expect( $component->autosaveInterval )->toBe( 60 );
	expect( $component->showSidebar )->toBeTrue();
	expect( $component->mode )->toBe( 'visual' );
	expect( $component->initialMeta )->toBe( [] );
	expect( $component->partSettings )->toBe( [] );
} );

test( 'template part editor accepts custom props', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$partSettings = [
		'name'        => 'Main Header',
		'slug'        => 'main-header',
		'area'        => 'header',
		'description' => 'The primary site header.',
		'status'      => 'active',
	];

	$component = new TemplatePartEditor(
		id: 'part-editor',
		autosave: true,
		autosaveInterval: 30,
		showSidebar: false,
		partSettings: $partSettings,
	);

	expect( $component->uuid )->toContain( 'part-editor' );
	expect( $component->autosave )->toBeTrue();
	expect( $component->autosaveInterval )->toBe( 30 );
	expect( $component->showSidebar )->toBeFalse();
	expect( $component->partSettings )->toBe( $partSettings );
} );

test( 'template part editor builds empty data when registry is empty', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplatePartEditor();

	expect( $component->inserterBlocks )->toBeArray()->toBeEmpty();
	expect( $component->renderedBlocks )->toBeArray()->toBeEmpty();
	expect( $component->defaultBlockTemplates )->toBeArray()->toBeEmpty();
	expect( $component->blockMetadata )->toBeArray()->toBeEmpty();
	expect( $component->inspectorBlockNames )->toBeArray()->toBeEmpty();
	expect( $component->inspectorBlockDescriptions )->toBeArray()->toBeEmpty();
	expect( $component->inspectorBlockTypes )->toBeArray()->toBeEmpty();
	expect( $component->toolbarBlockIcons )->toBeArray()->toBeEmpty();
	expect( $component->blockNames )->toBeArray()->toBeEmpty();
	expect( $component->transformableBlocks )->toBeArray()->toBeEmpty();
	expect( $component->blockAlignSupports )->toBeArray()->toBeEmpty();
	expect( $component->customToolbarHtml )->toBeArray()->toBeEmpty();
} );

test( 'template part editor builds editor shortcuts', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplatePartEditor();

	expect( $component->editorShortcuts )->toBeArray();
	expect( count( $component->editorShortcuts ) )->toBeGreaterThan( 0 );

	$first = $component->editorShortcuts[0];
	expect( $first )->toHaveKeys( [ 'name', 'keys', 'description', 'category' ] );
} );

test( 'template part editor icon renderer uses default when none provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplatePartEditor();

	expect( $component->iconRenderer )->toBeCallable();

	$result = ( $component->iconRenderer )( 'paragraph' );
	expect( $result )->toBeString();
	expect( $result )->toContain( 'svg' );
} );

test( 'template part editor icon renderer uses custom when provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$custom = function ( string $icon ): string {
		return '<i class="icon-' . $icon . '"></i>';
	};

	$component = new TemplatePartEditor( customIconRenderer: $custom );

	expect( $component->iconRenderer )->toBe( $custom );
	expect( ( $component->iconRenderer )( 'paragraph' ) )->toBe( '<i class="icon-paragraph"></i>' );
} );

test( 'template part editor builds patterns with previews', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$patterns = [
		[
			'name'   => 'test-pattern',
			'title'  => 'Test Pattern',
			'blocks' => [],
		],
	];

	$component = new TemplatePartEditor( patterns: $patterns );

	expect( $component->patternsWithPreviews )->toBeArray();
	expect( $component->patternsWithPreviews )->toHaveCount( 1 );
	expect( $component->patternsWithPreviews[0] )->toHaveKey( 'preview' );
} );

test( 'template part editor renders view name', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplatePartEditor();
	$view      = $component->render();

	expect( $view->name() )->toBe( 'visual-editor::components.template-part-editor' );
} );

test( 'template part editor auto-populates blockTransforms from registry', function (): void {
	$component = new TemplatePartEditor();

	expect( $component->blockTransforms )->toBeArray();
	expect( $component->blockTransforms )->toHaveKey( 'paragraph' );
} );
