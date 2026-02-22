<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\GalleryBlock;

test( 'gallery block has correct type and category', function (): void {
	$block = new GalleryBlock();

	expect( $block->getType() )->toBe( 'gallery' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'gallery block content schema has images and link behavior', function (): void {
	$block  = new GalleryBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'images' );
	expect( $schema )->toHaveKey( 'linkBehavior' );
	expect( $schema['images']['type'] )->toBe( 'repeater' );
} );

test( 'gallery block defaults to 3 columns', function (): void {
	$block    = new GalleryBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['columns'] )->toBe( '3' );
	expect( $defaults['gap'] )->toBe( 'medium' );
	expect( $defaults['crop'] )->toBeTrue();
} );

test( 'gallery block renders grid', function (): void {
	$block  = new GalleryBlock();
	$images = [
		[ 'url' => 'img1.jpg', 'alt' => 'Image 1', 'caption' => '' ],
		[ 'url' => 'img2.jpg', 'alt' => 'Image 2', 'caption' => '' ],
	];
	$output = $block->render( [ 'images' => $images ], [ 'columns' => '3', 'gap' => 'medium', 'captionDisplay' => 'below', 'crop' => true ] );

	expect( $output )->toContain( 'grid-template-columns' );
	expect( $output )->toContain( 'img1.jpg' );
	expect( $output )->toContain( 'img2.jpg' );
} );
