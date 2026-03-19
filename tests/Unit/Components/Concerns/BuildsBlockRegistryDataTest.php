<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\View\Components\Concerns\BuildsBlockRegistryData;

/**
 * Concrete test class that uses the trait.
 */
class TestTraitConsumer
{
	use BuildsBlockRegistryData {
		defaultIconRenderer as public;
		buildInserterBlocks as public;
		buildRenderedBlocks as public;
		buildDefaultBlockTemplates as public;
		buildBlockMetadata as public;
		buildInspectorData as public;
		buildToolbarData as public;
		buildAlignmentData as public;
		buildCustomPanels as public;
		buildEditorShortcuts as public;
		buildBlockTransforms as public;
		buildPatternsWithPreviews as public;
	}

	public array $initialBlocks = [];

	public array $patterns = [];

	public array $blockTransforms = [];

	public array $blockVariations = [];

	public Closure $iconRenderer;

	public array $inserterBlocks = [];

	public array $renderedBlocks = [];

	public array $defaultBlockTemplates = [];

	public array $blockMetadata = [];

	public array $inspectorBlockNames = [];

	public array $inspectorBlockDescriptions = [];

	public array $inspectorBlockTypes = [];

	public array $toolbarBlockIcons = [];

	public array $transformableBlocks = [];

	public array $blockNames = [];

	public array $blockAlignSupports = [];

	public array $customToolbarHtml = [];

	public array $editorShortcuts = [];

	public array $patternsWithPreviews = [];

	public array $defaultInnerBlocksMap = [];

	public function __construct()
	{
		$this->iconRenderer = $this->defaultIconRenderer();
	}
}

test( 'trait provides default icon renderer', function (): void {
	$consumer = new TestTraitConsumer();

	expect( $consumer->iconRenderer )->toBeCallable();

	$result = ( $consumer->iconRenderer )( '_default' );
	expect( $result )->toContain( 'svg' );
} );

test( 'trait builds editor shortcuts', function (): void {
	$consumer = new TestTraitConsumer();
	$consumer->buildEditorShortcuts();

	expect( $consumer->editorShortcuts )->toBeArray();
	expect( count( $consumer->editorShortcuts ) )->toBeGreaterThan( 0 );
	expect( $consumer->editorShortcuts[0] )->toHaveKeys( [ 'name', 'keys', 'description', 'category' ] );
} );

test( 'trait builds empty inserter blocks from empty registry', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildInserterBlocks( $registry );

	expect( $consumer->inserterBlocks )->toBeArray()->toBeEmpty();
	expect( $consumer->defaultInnerBlocksMap )->toBeArray()->toBeEmpty();
} );

test( 'trait builds empty rendered blocks when no initial blocks', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildRenderedBlocks( $registry );

	expect( $consumer->renderedBlocks )->toBeArray()->toBeEmpty();
} );

test( 'trait skips malformed initial blocks', function (): void {
	$consumer                = new TestTraitConsumer();
	$consumer->initialBlocks = [
		'not-an-array',
		[ 'id'   => 'no-type' ],
		[ 'type' => 'no-id' ],
	];

	$registry = new BlockRegistry();
	$consumer->buildRenderedBlocks( $registry );

	expect( $consumer->renderedBlocks )->toBeArray()->toBeEmpty();
} );

test( 'trait builds empty block metadata from empty registry', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildBlockMetadata( $registry );

	expect( $consumer->blockMetadata )->toBeArray()->toBeEmpty();
} );

test( 'trait builds empty inspector data from empty registry', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildInspectorData( $registry );

	expect( $consumer->inspectorBlockNames )->toBeArray()->toBeEmpty();
	expect( $consumer->inspectorBlockDescriptions )->toBeArray()->toBeEmpty();
	expect( $consumer->inspectorBlockTypes )->toBeArray()->toBeEmpty();
} );

test( 'trait builds empty toolbar data from empty registry', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildToolbarData( $registry );

	expect( $consumer->toolbarBlockIcons )->toBeArray()->toBeEmpty();
	expect( $consumer->transformableBlocks )->toBeArray()->toBeEmpty();
	expect( $consumer->blockNames )->toBeArray()->toBeEmpty();
} );

test( 'trait builds patterns with previews', function (): void {
	$consumer           = new TestTraitConsumer();
	$consumer->patterns = [
		[ 'name' => 'test-pattern', 'title' => 'Test' ],
	];
	$consumer->buildPatternsWithPreviews();

	expect( $consumer->patternsWithPreviews )->toHaveCount( 1 );
	expect( $consumer->patternsWithPreviews[0] )->toHaveKey( 'preview' );
} );

test( 'trait handles non-array patterns gracefully', function (): void {
	$consumer           = new TestTraitConsumer();
	$consumer->patterns = [
		'not-an-array',
		[ 'name' => 'valid', 'title' => 'Valid' ],
	];
	$consumer->buildPatternsWithPreviews();

	expect( $consumer->patternsWithPreviews )->toHaveCount( 2 );
	expect( $consumer->patternsWithPreviews[0] )->toBe( [] );
	expect( $consumer->patternsWithPreviews[1] )->toHaveKey( 'preview' );
} );

test( 'trait builds empty block transforms from empty registry', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildBlockTransforms( $registry );

	expect( $consumer->blockTransforms )->toBeArray()->toBeEmpty();
} );

test( 'trait builds empty alignment data from empty registry', function (): void {
	$consumer = new TestTraitConsumer();
	$registry = new BlockRegistry();
	$consumer->buildAlignmentData( $registry );

	expect( $consumer->blockAlignSupports )->toBeArray()->toBeEmpty();
} );

test( 'trait builds populated data from real registry', function (): void {
	// Use the real registry with core blocks (service provider boots automatically).
	$registry = app( 'visual-editor.blocks' );
	$consumer = new TestTraitConsumer();

	$consumer->buildInserterBlocks( $registry );
	expect( $consumer->inserterBlocks )->toBeArray()->not->toBeEmpty();
	expect( $consumer->inserterBlocks[0] )->toHaveKeys( [ 'name', 'label', 'icon', 'category' ] );

	$consumer->buildBlockMetadata( $registry );
	expect( $consumer->blockMetadata )->toBeArray()->not->toBeEmpty();
	expect( $consumer->blockMetadata )->toHaveKey( 'paragraph' );

	$consumer->buildInspectorData( $registry );
	expect( $consumer->inspectorBlockNames )->toBeArray()->not->toBeEmpty();
	expect( $consumer->inspectorBlockNames )->toHaveKey( 'paragraph' );
	expect( $consumer->inspectorBlockDescriptions )->toBeArray()->not->toBeEmpty();
	expect( $consumer->inspectorBlockTypes )->toBeArray()->not->toBeEmpty();
	expect( $consumer->inspectorBlockTypes )->toContain( 'paragraph' );

	$consumer->buildToolbarData( $registry );
	expect( $consumer->toolbarBlockIcons )->toBeArray()->not->toBeEmpty();
	expect( $consumer->toolbarBlockIcons )->toHaveKey( 'paragraph' );
	expect( $consumer->blockNames )->toBeArray()->not->toBeEmpty();
	expect( $consumer->blockNames )->toHaveKey( 'paragraph' );
	expect( $consumer->transformableBlocks )->toBeArray()->not->toBeEmpty();

	$consumer->buildDefaultBlockTemplates( $registry );
	expect( $consumer->defaultBlockTemplates )->toBeArray()->not->toBeEmpty();
	expect( $consumer->defaultBlockTemplates )->toHaveKey( 'paragraph' );
} );

test( 'trait builds rendered blocks from real registry with initial blocks', function (): void {
	$registry                = app( 'visual-editor.blocks' );
	$consumer                = new TestTraitConsumer();
	$consumer->initialBlocks = [
		[ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [ 'text' => 'Hello' ] ],
		[ 'id' => 'b2', 'type' => 'heading', 'attributes' => [ 'text' => 'Title', 'level' => 'h2' ] ],
	];

	$consumer->buildRenderedBlocks( $registry );

	expect( $consumer->renderedBlocks )->toBeArray()->not->toBeEmpty();
	expect( $consumer->renderedBlocks )->toHaveKey( 'b1' );
	expect( $consumer->renderedBlocks )->toHaveKey( 'b2' );
	expect( $consumer->renderedBlocks['b1'] )->toContain( 'Hello' );
} );

test( 'trait default icon renderer returns svg for heading', function (): void {
	$consumer = new TestTraitConsumer();
	$result   = ( $consumer->iconRenderer )( 'heading' );

	expect( $result )->toContain( 'svg' );
	expect( $result )->toContain( 'H' );
} );
