<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\ImageBlock;

test( 'image block has correct type and category', function (): void {
	$block = new ImageBlock();

	expect( $block->getType() )->toBe( 'image' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'image block content schema has url alt caption link fields', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'alt' );
	expect( $schema )->toHaveKey( 'caption' );
	expect( $schema )->toHaveKey( 'link' );
	expect( $schema )->toHaveKey( 'linkTarget' );
} );

test( 'image block url field uses media picker type', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getContentSchema();

	expect( $schema['url']['type'] )->toBe( 'media_picker' );
} );

test( 'image block alt field uses textarea type', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getContentSchema();

	expect( $schema['alt']['type'] )->toBe( 'textarea' );
} );

test( 'image block style schema has size rounded shadow and object fit fields', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'size' );
	expect( $schema )->toHaveKey( 'rounded' );
	expect( $schema )->toHaveKey( 'shadow' );
	expect( $schema )->toHaveKey( 'objectFit' );
} );

test( 'image block style schema has aspect ratio width height and resolution fields', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'aspectRatio' );
	expect( $schema['aspectRatio']['type'] )->toBe( 'select' );
	expect( $schema['aspectRatio']['default'] )->toBe( 'original' );

	expect( $schema )->toHaveKey( 'width' );
	expect( $schema['width']['type'] )->toBe( 'text' );

	expect( $schema )->toHaveKey( 'height' );
	expect( $schema['height']['type'] )->toBe( 'text' );

	expect( $schema )->toHaveKey( 'resolution' );
	expect( $schema['resolution']['type'] )->toBe( 'select' );
	expect( $schema['resolution']['default'] )->toBe( 'full' );
} );

test( 'image block defaults to large size', function (): void {
	$block    = new ImageBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['size'] )->toBe( 'large' );
} );

test( 'image block renders figure with img', function (): void {
	$block  = new ImageBlock();
	$output = $block->render( [ 'url' => 'test.jpg', 'alt' => 'Test' ], [ 'size' => 'large', 'alignment' => 'center' ] );

	expect( $output )->toContain( '<figure' );
	expect( $output )->toContain( '<img' );
	expect( $output )->toContain( 'test.jpg' );
} );

test( 'image block renders link wrapper when link is set', function (): void {
	$block  = new ImageBlock();
	$output = $block->render(
		[ 'url' => 'test.jpg', 'alt' => 'Test', 'link' => 'https://example.com', 'linkTarget' => '_blank' ],
		[ 'size' => 'large', 'alignment' => 'center' ],
	);

	expect( $output )->toContain( '<a href="https://example.com"' );
	expect( $output )->toContain( 'target="_blank"' );
} );

test( 'image block save view renders aspect ratio in inline style', function (): void {
	$block  = new ImageBlock();
	$output = $block->render(
		[ 'url' => 'test.jpg', 'alt' => 'Test' ],
		[ 'size' => 'large', 'alignment' => 'center', 'aspectRatio' => '16/9' ],
	);

	expect( $output )->toContain( 'aspect-ratio: 16/9' );
} );

test( 'image block save view renders width and height in inline style', function (): void {
	$block  = new ImageBlock();
	$output = $block->render(
		[ 'url' => 'test.jpg', 'alt' => 'Test' ],
		[ 'size' => 'large', 'alignment' => 'center', 'width' => '500', 'height' => '300' ],
	);

	expect( $output )->toContain( 'width: 500px' );
	expect( $output )->toContain( 'height: 300px' );
} );

test( 'image block save view does not render aspect ratio when original', function (): void {
	$block  = new ImageBlock();
	$output = $block->render(
		[ 'url' => 'test.jpg', 'alt' => 'Test' ],
		[ 'size' => 'large', 'alignment' => 'center', 'aspectRatio' => 'original' ],
	);

	expect( $output )->not->toContain( 'aspect-ratio' );
} );

test( 'image block supports alignment', function (): void {
	$block = new ImageBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'image block supports shadow and aspect ratio but not min height', function (): void {
	$block = new ImageBlock();

	expect( $block->supportsFeature( 'shadow' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeFalse();
} );

test( 'image block active style supports include shadow and dimensions aspect ratio', function (): void {
	$block   = new ImageBlock();
	$active  = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'shadow' );
	expect( $active )->toContain( 'dimensions.aspectRatio' );
	expect( $active )->not->toContain( 'dimensions.minHeight' );
} );

test( 'image block has toolbar controls with image actions group', function (): void {
	$block    = new ImageBlock();
	$controls = $block->getToolbarControls();

	$groups = array_column( $controls, 'group' );
	expect( $groups )->toContain( 'image-actions' );

	$imageGroup = collect( $controls )->firstWhere( 'group', 'image-actions' );
	$fields     = array_column( $imageGroup['controls'], 'field' );
	expect( $fields )->toContain( 'replace' );
	expect( $fields )->toContain( 'link' );
} );
