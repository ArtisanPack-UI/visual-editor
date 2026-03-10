<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\GridBlock;

test( 'grid block has correct type and category', function (): void {
	$block = new GridBlock();

	expect( $block->getType() )->toBe( 'grid' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'grid block content schema has columns and templateRows fields', function (): void {
	$block  = new GridBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'columns' );
	expect( $schema )->toHaveKey( 'templateRows' );
	expect( $schema['columns']['type'] )->toBe( 'responsive_range' );
	expect( $schema['columns']['min'] )->toBe( 1 );
	expect( $schema['columns']['max'] )->toBe( 12 );
	expect( $schema['columns']['step'] )->toBe( 1 );
	expect( $schema['templateRows']['type'] )->toBe( 'text' );
} );

test( 'grid block style schema has gap rowGap alignItems and justifyItems fields', function (): void {
	$block  = new GridBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'gap' );
	expect( $schema )->toHaveKey( 'rowGap' );
	expect( $schema )->toHaveKey( 'alignItems' );
	expect( $schema )->toHaveKey( 'justifyItems' );
	expect( $schema['gap']['type'] )->toBe( 'select' );
	expect( $schema['rowGap']['type'] )->toBe( 'select' );
	expect( $schema['alignItems']['type'] )->toBe( 'select' );
	expect( $schema['justifyItems']['type'] )->toBe( 'select' );
} );

test( 'grid block defaults to 3 columns global with medium gap and stretch alignment', function (): void {
	$block           = new GridBlock();
	$contentDefaults = $block->getDefaultContent();
	$styleDefaults   = $block->getDefaultStyles();

	expect( $contentDefaults['columns'] )->toBe( [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ] );
	expect( $contentDefaults['templateRows'] )->toBe( 'auto' );
	expect( $styleDefaults['gap'] )->toBe( 'medium' );
	expect( $styleDefaults['rowGap'] )->toBe( '' );
	expect( $styleDefaults['alignItems'] )->toBe( 'stretch' );
	expect( $styleDefaults['justifyItems'] )->toBe( 'stretch' );
} );

test( 'grid block only allows grid-item children', function (): void {
	$block = new GridBlock();

	expect( $block->getAllowedChildren() )->toBe( [ 'grid-item' ] );
} );

test( 'grid block renders with grid layout', function (): void {
	$block  = new GridBlock();
	$output = $block->render(
		[ 'columns' => [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ], 'templateRows' => 'auto' ],
		[ 'gap' => 'medium', 'rowGap' => '', 'alignItems' => 'stretch', 'justifyItems' => 'stretch' ],
	);

	expect( $output )->toContain( 've-block-grid' );
	expect( $output )->toContain( 'display: grid' );
	expect( $output )->toContain( 'grid-template-columns: repeat(3, 1fr)' );
} );

test( 'grid block supports alignment anchor className minHeight and background', function (): void {
	$block = new GridBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
	expect( $block->supportsFeature( 'anchor' ) )->toBeTrue();
	expect( $block->supportsFeature( 'className' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeFalse();
	expect( $block->supportsFeature( 'background.backgroundImage' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundSize' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundPosition' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundGradient' ) )->toBeTrue();
} );

test( 'grid block active style supports include dimensions and background', function (): void {
	$block  = new GridBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'dimensions.minHeight' );
	expect( $active )->not->toContain( 'dimensions.aspectRatio' );
	expect( $active )->toContain( 'background.backgroundImage' );
	expect( $active )->toContain( 'background.backgroundSize' );
	expect( $active )->toContain( 'background.backgroundPosition' );
	expect( $active )->toContain( 'background.backgroundGradient' );
} );

test( 'grid block is public', function (): void {
	$block = new GridBlock();

	expect( $block->isPublic() )->toBeTrue();
} );
