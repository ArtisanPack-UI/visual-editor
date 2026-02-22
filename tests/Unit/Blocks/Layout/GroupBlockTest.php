<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\GroupBlock;

test( 'group block has correct type and category', function (): void {
	$block = new GroupBlock();

	expect( $block->getType() )->toBe( 'group' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'group block content schema has tag field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'tag' );
	expect( $schema['tag']['type'] )->toBe( 'select' );
	expect( $schema['tag']['options'] )->toHaveKey( 'div' );
	expect( $schema['tag']['options'] )->toHaveKey( 'section' );
} );

test( 'group block style schema has all expected fields', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'padding' );
	expect( $schema )->toHaveKey( 'margin' );
	expect( $schema )->toHaveKey( 'border' );
	expect( $schema )->toHaveKey( 'borderRadius' );
	expect( $schema )->toHaveKey( 'minHeight' );
	expect( $schema )->toHaveKey( 'verticalAlignment' );
} );

test( 'group block defaults to div tag', function (): void {
	$block    = new GroupBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['tag'] )->toBe( 'div' );
} );

test( 'group block renders with correct html tag', function (): void {
	$block  = new GroupBlock();
	$output = $block->render( [ 'tag' => 'section' ], [ 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( '<section' );
	expect( $output )->toContain( '</section>' );
	expect( $output )->toContain( 've-block-group' );
} );

test( 'group block renders with background color', function (): void {
	$block  = new GroupBlock();
	$output = $block->render( [ 'tag' => 'div' ], [ 'backgroundColor' => '#ff0000', 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( 'background-color: #ff0000' );
} );

test( 'group block supports spacing and border', function (): void {
	$block = new GroupBlock();

	expect( $block->supportsFeature( 'spacing.margin' ) )->toBeTrue();
	expect( $block->supportsFeature( 'spacing.padding' ) )->toBeTrue();
	expect( $block->supportsFeature( 'border' ) )->toBeTrue();
	expect( $block->supportsFeature( 'color.background' ) )->toBeTrue();
} );

test( 'group block has keywords', function (): void {
	$block = new GroupBlock();

	expect( $block->getKeywords() )->toContain( 'container' );
	expect( $block->getKeywords() )->toContain( 'group' );
} );
