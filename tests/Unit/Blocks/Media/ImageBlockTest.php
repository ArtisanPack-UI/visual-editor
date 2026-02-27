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

test( 'image block style schema has size rounded shadow and object fit fields', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'size' );
	expect( $schema )->toHaveKey( 'rounded' );
	expect( $schema )->toHaveKey( 'shadow' );
	expect( $schema )->toHaveKey( 'objectFit' );
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
