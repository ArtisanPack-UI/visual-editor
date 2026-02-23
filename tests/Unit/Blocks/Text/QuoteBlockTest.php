<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\QuoteBlock;

test( 'quote block has correct type', function (): void {
	$block = new QuoteBlock();

	expect( $block->getType() )->toBe( 'quote' );
} );

test( 'quote block has correct category', function (): void {
	$block = new QuoteBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'quote block content schema has text and citation', function (): void {
	$block  = new QuoteBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'text' );
	expect( $schema )->toHaveKey( 'citation' );
} );

test( 'quote block style schema has style variants', function (): void {
	$block  = new QuoteBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'style' );
	expect( $schema['style']['options'] )->toHaveKey( 'default' );
	expect( $schema['style']['options'] )->toHaveKey( 'large' );
	expect( $schema['style']['options'] )->toHaveKey( 'pull-left' );
	expect( $schema['style']['options'] )->toHaveKey( 'pull-right' );
} );

test( 'quote block transforms to paragraph and heading', function (): void {
	$block      = new QuoteBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'paragraph' );
	expect( $transforms )->toHaveKey( 'heading' );
} );

test( 'quote block renders blockquote', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'A wise quote', 'citation' => 'Author' ],
		[ 'alignment' => 'left', 'style' => 'default' ],
	);

	expect( $output )->toContain( '<blockquote' );
	expect( $output )->toContain( 'A wise quote' );
	expect( $output )->toContain( '<cite>' );
	expect( $output )->toContain( 'Author' );
} );

test( 'quote block renders without citation', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'No citation', 'citation' => '' ],
		[ 'alignment' => 'left', 'style' => 'default' ],
	);

	expect( $output )->toContain( 'No citation' );
	expect( $output )->not->toContain( '<cite>' );
} );

test( 'quote block renders style variant class', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Large quote' ],
		[ 'alignment' => 'center', 'style' => 'large' ],
	);

	expect( $output )->toContain( 've-block-quote--large' );
	expect( $output )->toContain( 'text-center' );
} );

test( 'quote block editor has enter new block attribute', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor( [ 'text' => 'Hello' ], [ 'alignment' => 'left', 'style' => 'default' ] );

	expect( $output )->toContain( 'data-ve-enter-new-block' );
} );
