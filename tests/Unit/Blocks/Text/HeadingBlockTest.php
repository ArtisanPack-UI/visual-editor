<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\HeadingBlock;

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

	expect( $block->getIcon() )->toBe( 'h1' );
} );

test( 'heading block content schema has text and level fields', function (): void {
	$block  = new HeadingBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'text' );
	expect( $schema )->toHaveKey( 'level' );
	expect( $schema['text']['type'] )->toBe( 'rich_text' );
	expect( $schema['level']['type'] )->toBe( 'select' );
} );

test( 'heading block style schema has alignment and color fields', function (): void {
	$block  = new HeadingBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'alignment' );
	expect( $schema )->toHaveKey( 'textColor' );
	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'fontSize' );
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
