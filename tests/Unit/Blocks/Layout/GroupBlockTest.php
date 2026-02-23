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

test( 'group block content schema has flexDirection field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'flexDirection' );
	expect( $schema['flexDirection']['type'] )->toBe( 'select' );
	expect( $schema['flexDirection']['default'] )->toBe( 'column' );
} );

test( 'group block content schema has flexWrap field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'flexWrap' );
	expect( $schema['flexWrap']['type'] )->toBe( 'select' );
	expect( $schema['flexWrap']['default'] )->toBe( 'nowrap' );
} );

test( 'group block style schema has all expected fields', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'padding' );
	expect( $schema )->toHaveKey( 'margin' );
	expect( $schema )->toHaveKey( 'border' );
	expect( $schema['border']['type'] )->toBe( 'border' );
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

test( 'group block renders with flex direction row', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div', 'flexDirection' => 'row', 'flexWrap' => 'nowrap' ],
		[ 'verticalAlignment' => 'top' ],
	);

	expect( $output )->toContain( 'flex-direction: row' );
	expect( $output )->toContain( 'flex-wrap: nowrap' );
} );

test( 'group block has three variations', function (): void {
	$block      = new GroupBlock();
	$variations = $block->getVariations();

	expect( $variations )->toHaveCount( 3 );
} );

test( 'group block variations include group row and stack', function (): void {
	$block = new GroupBlock();
	$names = array_column( $block->getVariations(), 'name' );

	expect( $names )->toContain( 'group' );
	expect( $names )->toContain( 'row' );
	expect( $names )->toContain( 'stack' );
} );

test( 'group block has one default variation', function (): void {
	$block    = new GroupBlock();
	$defaults = array_filter( $block->getVariations(), fn ( $v ) => $v['isDefault'] );

	expect( $defaults )->toHaveCount( 1 );
	expect( array_values( $defaults )[0]['name'] )->toBe( 'group' );
} );

test( 'group block row variation has row flex direction', function (): void {
	$block      = new GroupBlock();
	$variations = $block->getVariations();
	$row        = array_values( array_filter( $variations, fn ( $v ) => 'row' === $v['name'] ) )[0];

	expect( $row['attributes']['flexDirection'] )->toBe( 'row' );
} );

test( 'group block has keywords', function (): void {
	$block = new GroupBlock();

	expect( $block->getKeywords() )->toContain( 'container' );
	expect( $block->getKeywords() )->toContain( 'group' );
} );
