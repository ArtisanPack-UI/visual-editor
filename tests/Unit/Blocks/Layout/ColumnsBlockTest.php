<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\ColumnsBlock;

test( 'columns block has correct type and category', function (): void {
	$block = new ColumnsBlock();

	expect( $block->getType() )->toBe( 'columns' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'columns block content schema has columns and layout fields', function (): void {
	$block  = new ColumnsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'columns' );
	expect( $schema )->toHaveKey( 'layout' );
	expect( $schema['columns']['type'] )->toBe( 'select' );
	expect( $schema['layout']['type'] )->toBe( 'select' );
} );

test( 'columns block style schema has gap vertical alignment and stack fields', function (): void {
	$block  = new ColumnsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'gap' );
	expect( $schema )->toHaveKey( 'verticalAlignment' );
	expect( $schema )->toHaveKey( 'stackOnMobile' );
} );

test( 'columns block defaults to 2 columns with equal layout', function (): void {
	$block    = new ColumnsBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['columns'] )->toBe( '2' );
	expect( $defaults['layout'] )->toBe( 'equal' );
} );

test( 'columns block defaults to medium gap and stack on mobile', function (): void {
	$block    = new ColumnsBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['gap'] )->toBe( 'medium' );
	expect( $defaults['stackOnMobile'] )->toBeTrue();
} );

test( 'columns block only allows column children', function (): void {
	$block = new ColumnsBlock();

	expect( $block->getAllowedChildren() )->toBe( [ 'column' ] );
} );

test( 'columns block renders with flex layout', function (): void {
	$block  = new ColumnsBlock();
	$output = $block->render( [ 'columns' => '3', 'layout' => 'equal' ], [ 'gap' => 'medium', 'verticalAlignment' => 'top', 'stackOnMobile' => true ] );

	expect( $output )->toContain( 've-block-columns' );
	expect( $output )->toContain( 'display: flex' );
	expect( $output )->toContain( 'data-columns="3"' );
} );

test( 'columns block supports alignment and anchor', function (): void {
	$block = new ColumnsBlock();

	expect( $block->supportsFeature( 'anchor' ) )->toBeTrue();
	expect( $block->supportsFeature( 'className' ) )->toBeTrue();
	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'columns block has keywords', function (): void {
	$block = new ColumnsBlock();

	expect( $block->getKeywords() )->toContain( 'grid' );
	expect( $block->getKeywords() )->toContain( 'columns' );
} );
