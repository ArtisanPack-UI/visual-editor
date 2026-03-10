<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock;
use ArtisanPackUI\VisualEditor\Blocks\Text\HeadingBlock as LegacyHeadingBlock;

test( 'heading block has correct type', function (): void {
	$block = new HeadingBlock();

	expect( $block->getType() )->toBe( 'heading' );
} );

test( 'heading block has correct category', function (): void {
	$block = new HeadingBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'heading block has correct icon', function (): void {
	$block = new HeadingBlock();

	expect( $block->getIcon() )->toBe( 'heading' );
} );

test( 'heading block content schema is empty for inline editing', function (): void {
	$block  = new HeadingBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'heading block style schema has color, font size, padding and margin fields', function (): void {
	$block  = new HeadingBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'textColor' );
	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'fontSize' );
	expect( $schema )->toHaveKey( 'padding' );
	expect( $schema )->toHaveKey( 'margin' );
	expect( $schema['padding']['type'] )->toBe( 'spacing' );
	expect( $schema['margin']['type'] )->toBe( 'spacing' );
} );

test( 'heading block default content has h2 level', function (): void {
	$block    = new HeadingBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['level'] )->toBe( 'h2' );
	expect( $defaults['text'] )->toBe( '' );
} );

test( 'heading block default styles have left alignment', function (): void {
	$block    = new HeadingBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['alignment'] )->toBe( 'left' );
} );

test( 'heading block transforms to paragraph and quote', function (): void {
	$block      = new HeadingBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'paragraph' );
	expect( $transforms )->toHaveKey( 'quote' );
} );

test( 'heading block supports spacing', function (): void {
	$block = new HeadingBlock();

	expect( $block->supportsFeature( 'spacing.margin' ) )->toBeTrue();
	expect( $block->supportsFeature( 'spacing.padding' ) )->toBeTrue();
} );

test( 'heading block renders with padding and margin styles', function (): void {
	$block  = new HeadingBlock();
	$output = $block->render(
		[ 'text' => 'Spaced Heading', 'level' => 'h2' ],
		[
			'alignment' => 'left',
			'padding'   => [ 'top' => '10px', 'right' => '20px', 'bottom' => '10px', 'left' => '20px' ],
			'margin'    => [ 'top' => '5px', 'bottom' => '15px' ],
		],
	);

	expect( $output )->toContain( 'padding: 10px 20px 10px 20px' );
	expect( $output )->toContain( 'margin-top: 5px' );
	expect( $output )->toContain( 'margin-bottom: 15px' );
} );

test( 'heading block supports color and typography', function (): void {
	$block = new HeadingBlock();

	expect( $block->supportsFeature( 'color.text' ) )->toBeTrue();
	expect( $block->supportsFeature( 'color.background' ) )->toBeTrue();
	expect( $block->supportsFeature( 'typography.fontSize' ) )->toBeTrue();
} );

test( 'heading block renders h2 by default', function (): void {
	$block  = new HeadingBlock();
	$output = $block->render( [ 'text' => 'Test Heading', 'level' => 'h2' ], [ 'alignment' => 'left' ] );

	expect( $output )->toContain( '<h2' );
	expect( $output )->toContain( 'Test Heading' );
	expect( $output )->toContain( '</h2>' );
} );

test( 'heading block renders correct level', function (): void {
	$block  = new HeadingBlock();
	$output = $block->render( [ 'text' => 'H3 Heading', 'level' => 'h3' ], [ 'alignment' => 'center' ] );

	expect( $output )->toContain( '<h3' );
	expect( $output )->toContain( 'text-center' );
} );

test( 'heading block is public', function (): void {
	$block = new HeadingBlock();

	expect( $block->isPublic() )->toBeTrue();
} );

test( 'heading block has keywords', function (): void {
	$block = new HeadingBlock();

	expect( $block->getKeywords() )->toContain( 'title' );
	expect( $block->getKeywords() )->toContain( 'h1' );
} );

test( 'heading block editor has enter new block attribute', function (): void {
	$block  = new HeadingBlock();
	$output = $block->renderEditor( [ 'text' => 'Hello', 'level' => 'h2' ], [ 'alignment' => 'left' ] );

	expect( $output )->toContain( 'data-ve-enter-new-block' );
} );

test( 'heading block loads metadata from block.json', function (): void {
	$block    = new HeadingBlock();
	$metadata = $block->getMetadata();

	expect( $metadata )->not->toBeNull();
	expect( $metadata['type'] )->toBe( 'heading' );
	expect( $metadata['name'] )->toBe( 'Heading' );
	expect( $metadata['category'] )->toBe( 'text' );
} );

test( 'heading block has attributes from block.json', function (): void {
	$block      = new HeadingBlock();
	$attributes = $block->getAttributes();

	expect( $attributes )->toHaveKey( 'text' );
	expect( $attributes )->toHaveKey( 'level' );
	expect( $attributes )->toHaveKey( 'alignment' );
	expect( $attributes )->toHaveKey( 'textColor' );
	expect( $attributes )->toHaveKey( 'backgroundColor' );
	expect( $attributes )->toHaveKey( 'fontSize' );

	expect( $attributes['text']['type'] )->toBe( 'rich_text' );
	expect( $attributes['text']['source'] )->toBe( 'content' );
	expect( $attributes['level']['source'] )->toBe( 'content' );
	expect( $attributes['alignment']['source'] )->toBe( 'style' );
} );

test( 'heading block has translation-aware name', function (): void {
	$block = new HeadingBlock();

	expect( $block->getName() )->toBe( 'Heading' );
} );

test( 'heading block has translation-aware description', function (): void {
	$block = new HeadingBlock();

	expect( $block->getDescription() )->toBe( 'Add a heading to your content' );
} );

test( 'heading block has custom toolbar', function (): void {
	$block = new HeadingBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );

test( 'heading block does not have custom inspector', function (): void {
	$block = new HeadingBlock();

	expect( $block->hasCustomInspector() )->toBeFalse();
} );

test( 'heading block view resolution falls back correctly', function (): void {
	$block  = new HeadingBlock();
	$output = $block->render( [ 'text' => 'Fallback', 'level' => 'h2' ], [ 'alignment' => 'left' ] );

	expect( $output )->toContain( 'Fallback' );
	expect( $output )->toContain( '<h2' );
} );

test( 'heading block supports from block.json', function (): void {
	$block    = new HeadingBlock();
	$supports = $block->getSupports();

	expect( $supports['align'] )->toBe( [ 'left', 'center', 'right', 'wide', 'full' ] );
	expect( $supports['color']['text'] )->toBeTrue();
	expect( $supports['color']['background'] )->toBeTrue();
	expect( $supports['typography']['fontSize'] )->toBeTrue();
	expect( $supports['anchor'] )->toBeTrue();
	expect( $supports['className'] )->toBeTrue();
} );

test( 'legacy namespace alias works', function (): void {
	$block = new LegacyHeadingBlock();

	expect( $block->getType() )->toBe( 'heading' );
	expect( $block )->toBeInstanceOf( HeadingBlock::class );
} );

test( 'heading block version comes from block.json', function (): void {
	$block = new HeadingBlock();

	expect( $block->getVersion() )->toBe( 1 );
} );
