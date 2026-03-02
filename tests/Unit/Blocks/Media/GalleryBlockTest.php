<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\GalleryBlock;

test( 'gallery block has correct type and category', function (): void {
	$block = new GalleryBlock();

	expect( $block->getType() )->toBe( 'gallery' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'gallery block content schema has link behavior but no images', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'linkBehavior' );
	expect( $schema )->not->toHaveKey( 'images' );
	expect( $schema['linkBehavior']['type'] )->toBe( 'select' );
	expect( $schema['linkBehavior']['default'] )->toBe( 'none' );
} );

test( 'gallery block content schema has columns as responsive range control', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'columns' );
	expect( $schema['columns']['type'] )->toBe( 'responsive_range' );
	expect( $schema['columns']['min'] )->toBe( 1 );
	expect( $schema['columns']['max'] )->toBe( 6 );
	expect( $schema['columns']['step'] )->toBe( 1 );
	expect( $schema['columns']['default'] )->toBe( [
		'mode'    => 'global',
		'global'  => 3,
		'desktop' => 3,
		'tablet'  => 2,
		'mobile'  => 1,
	] );
} );

test( 'gallery block content schema has gap as range control', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'gap' );
	expect( $schema['gap']['type'] )->toBe( 'range' );
	expect( $schema['gap']['min'] )->toBe( 0 );
	expect( $schema['gap']['max'] )->toBe( 3 );
	expect( $schema['gap']['step'] )->toBe( 0.25 );
	expect( $schema['gap']['default'] )->toBe( 1 );
} );

test( 'gallery block content schema has resolution select control', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'resolution' );
	expect( $schema['resolution']['type'] )->toBe( 'select' );
	expect( $schema['resolution']['default'] )->toBe( 'full' );
} );

test( 'gallery block content schema has crop toggle defaulting to true', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'crop' );
	expect( $schema['crop']['type'] )->toBe( 'toggle' );
	expect( $schema['crop']['default'] )->toBeTrue();
} );

test( 'gallery block content schema has randomize order toggle defaulting to false', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'randomizeOrder' );
	expect( $schema['randomizeOrder']['type'] )->toBe( 'toggle' );
	expect( $schema['randomizeOrder']['default'] )->toBeFalse();
} );

test( 'gallery block content schema has open in new tab toggle defaulting to false', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'openInNewTab' );
	expect( $schema['openInNewTab']['type'] )->toBe( 'toggle' );
	expect( $schema['openInNewTab']['default'] )->toBeFalse();
} );

test( 'gallery block content schema has aspect ratio select defaulting to original', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'aspectRatio' );
	expect( $schema['aspectRatio']['type'] )->toBe( 'select' );
	expect( $schema['aspectRatio']['default'] )->toBe( 'original' );
} );

test( 'gallery block style schema is empty except for inherited controls', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->not->toHaveKey( 'captionDisplay' );
	expect( $schema )->not->toHaveKey( 'columns' );
} );

test( 'gallery block defaults to global mode with 3 columns medium gap and crop enabled', function (): void {
	$block    = new GalleryBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['columns'] )->toBe( [
		'mode'    => 'global',
		'global'  => 3,
		'desktop' => 3,
		'tablet'  => 2,
		'mobile'  => 1,
	] );
	expect( $defaults['gap'] )->toBe( 1 );
	expect( $defaults['crop'] )->toBeTrue();
	expect( $defaults['randomizeOrder'] )->toBeFalse();
	expect( $defaults['openInNewTab'] )->toBeFalse();
	expect( $defaults['aspectRatio'] )->toBe( 'original' );
	expect( $defaults['resolution'] )->toBe( 'full' );
} );

test( 'gallery block renders grid with grid template columns', function (): void {
	$block  = new GalleryBlock();
	$output = $block->render(
		[ 'columns' => [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ], 'gap' => 1, 'crop' => true, 'aspectRatio' => 'original' ],
		[],
	);

	expect( $output )->toContain( 'grid-template-columns' );
} );

test( 'gallery block has toolbar controls with gallery actions group', function (): void {
	$block    = new GalleryBlock();
	$controls = $block->getToolbarControls();

	$groups = array_column( $controls, 'group' );
	expect( $groups )->toContain( 'gallery-actions' );

	$galleryGroup = collect( $controls )->firstWhere( 'group', 'gallery-actions' );
	$fields       = array_column( $galleryGroup['controls'], 'field' );
	expect( $fields )->toContain( 'add' );
} );

test( 'gallery block allows image children', function (): void {
	$block    = new GalleryBlock();
	$children = $block->getAllowedChildren();

	expect( $children )->toBe( [ 'image' ] );
} );
