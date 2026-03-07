<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock;

test( 'list block has correct type', function (): void {
	$block = new ListBlock();

	expect( $block->getType() )->toBe( 'list' );
} );

test( 'list block has correct category', function (): void {
	$block = new ListBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'list block content schema has start and reversed fields', function (): void {
	$block  = new ListBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'start' );
	expect( $schema )->toHaveKey( 'reversed' );
	expect( $schema )->not->toHaveKey( 'items' );
	expect( $schema )->not->toHaveKey( 'type' );
} );

test( 'list block style schema has padding and margin fields', function (): void {
	$block  = new ListBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'padding' );
	expect( $schema )->toHaveKey( 'margin' );
	expect( $schema['padding']['type'] )->toBe( 'spacing' );
	expect( $schema['margin']['type'] )->toBe( 'spacing' );
} );

test( 'list block renders with padding and margin styles', function (): void {
	$block  = new ListBlock();
	$output = $block->render(
		[ 'type' => 'unordered' ],
		[
			'padding' => [ 'top' => '6px', 'right' => '12px', 'bottom' => '6px', 'left' => '12px' ],
			'margin'  => [ 'top' => '8px', 'bottom' => '8px' ],
		],
	);

	expect( $output )->toContain( 'padding: 6px 12px 6px 12px' );
	expect( $output )->toContain( 'margin-top: 8px' );
	expect( $output )->toContain( 'margin-bottom: 8px' );
} );

test( 'list block transforms to paragraph', function (): void {
	$block      = new ListBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'paragraph' );
} );

test( 'list block renders unordered list', function (): void {
	$block  = new ListBlock();
	$output = $block->render( [ 'type' => 'unordered' ], [] );

	expect( $output )->toContain( '<ul' );
	expect( $output )->toContain( '<li>' );
	expect( $output )->toContain( 'list-disc' );
} );

test( 'list block renders ordered list', function (): void {
	$block  = new ListBlock();
	$output = $block->render( [ 'type' => 'ordered', 'start' => '1' ], [] );

	expect( $output )->toContain( '<ol' );
	expect( $output )->toContain( 'list-decimal' );
} );

test( 'list block renders reversed ordered list', function (): void {
	$block  = new ListBlock();
	$output = $block->render( [ 'type' => 'ordered', 'start' => '1', 'reversed' => true ], [] );

	expect( $output )->toContain( 'reversed' );
} );

test( 'list block renders with start number', function (): void {
	$block  = new ListBlock();
	$output = $block->render( [ 'type' => 'ordered', 'start' => '5' ], [] );

	expect( $output )->toContain( 'start="5"' );
} );

test( 'list block renders editor template with contenteditable', function (): void {
	$block  = new ListBlock();
	$output = $block->renderEditor( [ 'type' => 'unordered' ], [] );

	expect( $output )->toContain( 'contenteditable="true"' );
	expect( $output )->toContain( 'data-placeholder' );
} );

test( 'list block has custom toolbar', function (): void {
	$block = new ListBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );
