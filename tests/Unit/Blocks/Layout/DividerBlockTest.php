<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\DividerBlock;

test( 'divider block has correct type and category', function (): void {
	$block = new DividerBlock();

	expect( $block->getType() )->toBe( 'divider' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'divider block has empty content schema', function (): void {
	$block  = new DividerBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'divider block style schema has style width color and thickness fields', function (): void {
	$block  = new DividerBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'style' );
	expect( $schema )->toHaveKey( 'width' );
	expect( $schema )->toHaveKey( 'color' );
	expect( $schema )->toHaveKey( 'thickness' );
} );

test( 'divider block defaults to solid style and full width', function (): void {
	$block    = new DividerBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['style'] )->toBe( 'solid' );
	expect( $defaults['width'] )->toBe( 'full' );
	expect( $defaults['thickness'] )->toBe( '1px' );
} );

test( 'divider block renders hr element', function (): void {
	$block  = new DividerBlock();
	$output = $block->render( [], [ 'style' => 'solid', 'width' => 'full', 'thickness' => '1px' ] );

	expect( $output )->toContain( '<hr' );
	expect( $output )->toContain( 've-block-divider' );
	expect( $output )->toContain( 'border-top-style: solid' );
} );

test( 'divider block renders with custom color', function (): void {
	$block  = new DividerBlock();
	$output = $block->render( [], [ 'style' => 'dashed', 'width' => 'narrow', 'color' => '#333333', 'thickness' => '2px' ] );

	expect( $output )->toContain( 'border-top-style: dashed' );
	expect( $output )->toContain( 'border-top-color: #333333' );
	expect( $output )->toContain( 'border-top-width: 2px' );
	expect( $output )->toContain( 'width: 50%' );
} );

test( 'divider block has keywords', function (): void {
	$block = new DividerBlock();

	expect( $block->getKeywords() )->toContain( 'separator' );
	expect( $block->getKeywords() )->toContain( 'line' );
} );
