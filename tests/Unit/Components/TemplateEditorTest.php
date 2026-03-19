<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\View\Components\TemplateEditor;

test( 'template editor can be instantiated with defaults', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplateEditor();

	expect( $component->uuid )->toStartWith( 've-tpl-editor-' );
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
	expect( $component->templates )->toBe( [] );
	expect( $component->currentTemplateSlug )->toBe( '' );
} );

test( 'template editor accepts custom props', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$templates = [
		[ 'name' => 'Default Page', 'slug' => 'default-page' ],
		[ 'name' => 'Full Width', 'slug' => 'full-width' ],
	];

	$component = new TemplateEditor(
		id: 'tpl-editor',
		autosave: true,
		autosaveInterval: 30,
		showSidebar: false,
		templates: $templates,
		currentTemplateSlug: 'default-page',
	);

	expect( $component->uuid )->toContain( 'tpl-editor' );
	expect( $component->autosave )->toBeTrue();
	expect( $component->autosaveInterval )->toBe( 30 );
	expect( $component->showSidebar )->toBeFalse();
	expect( $component->templates )->toBe( $templates );
	expect( $component->currentTemplateSlug )->toBe( 'default-page' );
} );

test( 'template editor builds empty data when registry is empty', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplateEditor();

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

test( 'template editor builds editor shortcuts', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplateEditor();

	expect( $component->editorShortcuts )->toBeArray();
	expect( count( $component->editorShortcuts ) )->toBeGreaterThan( 0 );

	$first = $component->editorShortcuts[0];
	expect( $first )->toHaveKeys( [ 'name', 'keys', 'description', 'category' ] );
} );

test( 'template editor icon renderer uses default when none provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplateEditor();

	expect( $component->iconRenderer )->toBeCallable();

	$result = ( $component->iconRenderer )( 'paragraph' );
	expect( $result )->toBeString();
	expect( $result )->toContain( 'svg' );
} );

test( 'template editor icon renderer uses custom when provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$custom = function ( string $icon ): string {
		return '<i class="icon-' . $icon . '"></i>';
	};

	$component = new TemplateEditor( customIconRenderer: $custom );

	expect( $component->iconRenderer )->toBe( $custom );
	expect( ( $component->iconRenderer )( 'paragraph' ) )->toBe( '<i class="icon-paragraph"></i>' );
} );

test( 'template editor builds patterns with previews', function (): void {
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

	$component = new TemplateEditor( patterns: $patterns );

	expect( $component->patternsWithPreviews )->toBeArray();
	expect( $component->patternsWithPreviews )->toHaveCount( 1 );
	expect( $component->patternsWithPreviews[0] )->toHaveKey( 'preview' );
} );

test( 'template editor renders view name', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new TemplateEditor();
	$view      = $component->render();

	expect( $view->name() )->toBe( 'visual-editor::components.template-editor' );
} );

test( 'template editor auto-populates blockTransforms from registry', function (): void {
	// Use the real registry with all core blocks registered
	// (the service provider registers core blocks on boot).
	$component = new TemplateEditor();

	expect( $component->blockTransforms )->toBeArray();
	expect( $component->blockTransforms )->toHaveKey( 'paragraph' );
} );
