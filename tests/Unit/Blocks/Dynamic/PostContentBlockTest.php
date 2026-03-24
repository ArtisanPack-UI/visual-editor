<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostContent\PostContentBlock;

test( 'post content block has correct type', function (): void {
	$block = new PostContentBlock();

	expect( $block->getType() )->toBe( 'post-content' );
} );

test( 'post content block has correct category', function (): void {
	$block = new PostContentBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post content block has content schema with layout field', function (): void {
	$block  = new PostContentBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'layout' ] )
		->and( $schema['layout']['type'] )->toBe( 'select' )
		->and( $schema['layout']['default'] )->toBe( 'default' );
} );

test( 'post content block layout options include default wide and full', function (): void {
	$block   = new PostContentBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['layout']['options'] );

	expect( $options )->toContain( 'default', 'wide', 'full' );
} );

test( 'post content block default content has correct values', function (): void {
	$block    = new PostContentBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['layout'] )->toBe( 'default' );
} );

test( 'post content block has toolbar controls', function (): void {
	$block    = new PostContentBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'layout' );
} );

test( 'post content block has keywords', function (): void {
	$block = new PostContentBlock();

	expect( $block->getKeywords() )->toContain( 'post' )
		->and( $block->getKeywords() )->toContain( 'content' );
} );

test( 'post content block supports spacing', function (): void {
	$block    = new PostContentBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post content block is marked as dynamic', function (): void {
	$block = new PostContentBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
