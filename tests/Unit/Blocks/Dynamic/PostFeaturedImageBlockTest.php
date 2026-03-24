<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostFeaturedImage\PostFeaturedImageBlock;

test( 'post featured image block has correct type', function (): void {
	$block = new PostFeaturedImageBlock();

	expect( $block->getType() )->toBe( 'post-featured-image' );
} );

test( 'post featured image block has correct category', function (): void {
	$block = new PostFeaturedImageBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post featured image block has content schema with image fields', function (): void {
	$block  = new PostFeaturedImageBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'isLink', 'aspectRatio', 'width', 'height', 'scale' ] )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse()
		->and( $schema['aspectRatio']['type'] )->toBe( 'select' )
		->and( $schema['aspectRatio']['default'] )->toBe( '' )
		->and( $schema['width']['type'] )->toBe( 'unit' )
		->and( $schema['height']['type'] )->toBe( 'unit' )
		->and( $schema['scale']['type'] )->toBe( 'select' )
		->and( $schema['scale']['default'] )->toBe( 'cover' );
} );

test( 'post featured image block aspect ratio options include common ratios', function (): void {
	$block   = new PostFeaturedImageBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['aspectRatio']['options'] );

	expect( $options )->toContain( '', '1/1', '4/3', '16/9' );
} );

test( 'post featured image block scale options include cover and contain', function (): void {
	$block   = new PostFeaturedImageBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['scale']['options'] );

	expect( $options )->toContain( 'cover', 'contain' );
} );

test( 'post featured image block has style schema with overlay fields', function (): void {
	$block  = new PostFeaturedImageBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKeys( [ 'overlayColor', 'dimRatio' ] )
		->and( $schema['overlayColor']['type'] )->toBe( 'color' )
		->and( $schema['dimRatio']['type'] )->toBe( 'range' )
		->and( $schema['dimRatio']['min'] )->toBe( 0 )
		->and( $schema['dimRatio']['max'] )->toBe( 100 )
		->and( $schema['dimRatio']['default'] )->toBe( 0 );
} );

test( 'post featured image block default content has correct values', function (): void {
	$block    = new PostFeaturedImageBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['isLink'] )->toBeFalse()
		->and( $defaults['aspectRatio'] )->toBe( '' )
		->and( $defaults['scale'] )->toBe( 'cover' );
} );

test( 'post featured image block has keywords', function (): void {
	$block = new PostFeaturedImageBlock();

	expect( $block->getKeywords() )->toContain( 'featured' )
		->and( $block->getKeywords() )->toContain( 'image' );
} );

test( 'post featured image block supports spacing', function (): void {
	$block    = new PostFeaturedImageBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post featured image block supports border', function (): void {
	$block    = new PostFeaturedImageBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'border' )
		->and( $supports['border'] )->toBeTrue();
} );

test( 'post featured image block is marked as dynamic', function (): void {
	$block = new PostFeaturedImageBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
