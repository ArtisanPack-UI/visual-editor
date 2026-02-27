<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\ParagraphBlock;

test( 'paragraph block has correct type', function (): void {
	$block = new ParagraphBlock();

	expect( $block->getType() )->toBe( 'paragraph' );
} );

test( 'paragraph block has correct category', function (): void {
	$block = new ParagraphBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'paragraph block content schema is empty for inline editing', function (): void {
	$block  = new ParagraphBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'paragraph block style schema has color and font size fields', function (): void {
	$block  = new ParagraphBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'textColor' );
	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'fontSize' );
} );

test( 'paragraph block default styles include drop cap false', function (): void {
	$block    = new ParagraphBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['dropCap'] )->toBeFalse();
} );

test( 'paragraph block transforms to heading list and quote', function (): void {
	$block      = new ParagraphBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'heading' );
	expect( $transforms )->toHaveKey( 'list' );
	expect( $transforms )->toHaveKey( 'quote' );
} );

test( 'paragraph block renders p tag', function (): void {
	$block  = new ParagraphBlock();
	$output = $block->render( [ 'text' => 'Hello world' ], [ 'alignment' => 'left' ] );

	expect( $output )->toContain( '<p' );
	expect( $output )->toContain( 'Hello world' );
	expect( $output )->toContain( '</p>' );
} );

test( 'paragraph block renders with drop cap class', function (): void {
	$block  = new ParagraphBlock();
	$output = $block->render( [ 'text' => 'Drop cap text' ], [ 'alignment' => 'left', 'dropCap' => true ] );

	expect( $output )->toContain( 've-drop-cap' );
} );

test( 'paragraph block editor has enter new block attribute', function (): void {
	$block  = new ParagraphBlock();
	$output = $block->renderEditor( [ 'text' => 'Hello' ], [ 'alignment' => 'left' ] );

	expect( $output )->toContain( 'data-ve-enter-new-block' );
} );

test( 'paragraph block editor has slash command attribute', function (): void {
	$block  = new ParagraphBlock();
	$output = $block->renderEditor( [ 'text' => 'Hello' ], [ 'alignment' => 'left' ] );

	expect( $output )->toContain( 'data-ve-slash-command' );
} );
