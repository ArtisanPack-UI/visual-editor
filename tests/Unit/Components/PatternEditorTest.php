<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\View\Components\PatternEditor;

test( 'pattern editor can be instantiated with defaults', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new PatternEditor();

	expect( $component->uuid )->toStartWith( 've-pattern-editor-' );
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
	expect( $component->patternSettings )->toBe( [] );
} );

test( 'pattern editor accepts custom props', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$patternSettings = [
		'name'        => 'Hero Banner',
		'slug'        => 'hero-banner',
		'category'    => 'header',
		'description' => 'A hero banner pattern.',
		'keywords'    => 'hero, banner',
		'status'      => 'active',
		'isSynced'    => true,
	];

	$component = new PatternEditor(
		id: 'pattern-editor',
		autosave: true,
		autosaveInterval: 30,
		showSidebar: false,
		patternSettings: $patternSettings,
	);

	expect( $component->uuid )->toContain( 'pattern-editor' );
	expect( $component->autosave )->toBeTrue();
	expect( $component->autosaveInterval )->toBe( 30 );
	expect( $component->showSidebar )->toBeFalse();
	expect( $component->patternSettings )->toBe( $patternSettings );
} );

test( 'pattern editor builds empty data when registry is empty', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new PatternEditor();

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

test( 'pattern editor builds editor shortcuts', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new PatternEditor();

	expect( $component->editorShortcuts )->toBeArray();
	expect( count( $component->editorShortcuts ) )->toBeGreaterThan( 0 );

	$first = $component->editorShortcuts[0];
	expect( $first )->toHaveKeys( [ 'name', 'keys', 'description', 'category' ] );
} );

test( 'pattern editor icon renderer uses default when none provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new PatternEditor();

	expect( $component->iconRenderer )->toBeCallable();

	$result = ( $component->iconRenderer )( 'paragraph' );
	expect( $result )->toBeString();
	expect( $result )->toContain( 'svg' );
} );

test( 'pattern editor icon renderer uses custom when provided', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$custom = function ( string $icon ): string {
		return '<i class="icon-' . $icon . '"></i>';
	};

	$component = new PatternEditor( customIconRenderer: $custom );

	expect( $component->iconRenderer )->toBe( $custom );
	expect( ( $component->iconRenderer )( 'paragraph' ) )->toBe( '<i class="icon-paragraph"></i>' );
} );

test( 'pattern editor builds patterns with previews', function (): void {
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

	$component = new PatternEditor( patterns: $patterns );

	expect( $component->patternsWithPreviews )->toBeArray();
	expect( $component->patternsWithPreviews )->toHaveCount( 1 );
	expect( $component->patternsWithPreviews[0] )->toHaveKey( 'preview' );
} );

test( 'pattern editor renders view name', function (): void {
	$this->app->singleton( 'visual-editor.blocks', function () {
		return new BlockRegistry();
	} );

	$component = new PatternEditor();
	$view      = $component->render();

	expect( $view->name() )->toBe( 'visual-editor::components.pattern-editor' );
} );

test( 'pattern editor auto-populates blockTransforms from registry', function (): void {
	$component = new PatternEditor();

	expect( $component->blockTransforms )->toBeArray();
	expect( $component->blockTransforms )->toHaveKey( 'paragraph' );
} );
