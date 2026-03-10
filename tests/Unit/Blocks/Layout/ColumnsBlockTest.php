<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\ColumnsBlock;

test( 'columns block has correct type and category', function (): void {
	$block = new ColumnsBlock();

	expect( $block->getType() )->toBe( 'columns' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'columns block content schema has columns layout and isStacked fields', function (): void {
	$block  = new ColumnsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'columns' );
	expect( $schema )->toHaveKey( 'layout' );
	expect( $schema )->toHaveKey( 'isStacked' );
	expect( $schema['columns']['type'] )->toBe( 'responsive_range' );
	expect( $schema['columns']['min'] )->toBe( 1 );
	expect( $schema['columns']['max'] )->toBe( 6 );
	expect( $schema['columns']['step'] )->toBe( 1 );
	expect( $schema['columns']['inspector'] )->toBeFalse();
	expect( $schema['layout']['type'] )->toBe( 'select' );
	expect( $schema['layout']['inspector'] )->toBeFalse();
	expect( $schema['isStacked']['type'] )->toBe( 'toggle' );
	expect( $schema['isStacked']['inspector'] )->toBeFalse();
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

	expect( $defaults['columns'] )->toBe( [ 'mode' => 'global', 'global' => 2, 'desktop' => 2, 'tablet' => 2, 'mobile' => 1 ] );
	expect( $defaults['layout'] )->toBe( 'equal' );
	expect( $defaults['isStacked'] )->toBeFalse();
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
	$output = $block->render(
		[ 'columns' => [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ], 'layout' => 'equal' ],
		[ 'gap' => 'medium', 'verticalAlignment' => 'top', 'stackOnMobile' => true ],
	);

	expect( $output )->toContain( 've-block-columns' );
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

test( 'columns block supports min height and all background sub-keys', function (): void {
	$block = new ColumnsBlock();

	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeFalse();
	expect( $block->supportsFeature( 'background.backgroundImage' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundSize' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundPosition' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundGradient' ) )->toBeTrue();
} );

test( 'columns block active style supports include dimensions and background', function (): void {
	$block  = new ColumnsBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'dimensions.minHeight' );
	expect( $active )->not->toContain( 'dimensions.aspectRatio' );
	expect( $active )->toContain( 'background.backgroundImage' );
	expect( $active )->toContain( 'background.backgroundSize' );
	expect( $active )->toContain( 'background.backgroundPosition' );
	expect( $active )->toContain( 'background.backgroundGradient' );
} );

test( 'columns block has custom toolbar', function (): void {
	$block = new ColumnsBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );

test( 'columns block has custom inspector', function (): void {
	$block = new ColumnsBlock();

	expect( $block->hasCustomInspector() )->toBeTrue();
} );
