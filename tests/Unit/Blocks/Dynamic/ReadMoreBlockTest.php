<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\ReadMore\ReadMoreBlock;

test( 'read more block has correct type', function (): void {
	$block = new ReadMoreBlock();

	expect( $block->getType() )->toBe( 'read-more' );
} );

test( 'read more block has correct category', function (): void {
	$block = new ReadMoreBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'read more block has content schema with content link target and show arrow fields', function (): void {
	$block  = new ReadMoreBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'content', 'linkTarget', 'showArrow' ] )
		->and( $schema['content']['type'] )->toBe( 'text' )
		->and( $schema['linkTarget']['type'] )->toBe( 'select' )
		->and( $schema['linkTarget']['default'] )->toBe( '_self' )
		->and( $schema['showArrow']['type'] )->toBe( 'toggle' )
		->and( $schema['showArrow']['default'] )->toBeFalse();
} );

test( 'read more block link target options include self and blank', function (): void {
	$block   = new ReadMoreBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['linkTarget']['options'] );

	expect( $options )->toContain( '_self', '_blank' );
} );

test( 'read more block default content has correct values', function (): void {
	$block    = new ReadMoreBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['linkTarget'] )->toBe( '_self' )
		->and( $defaults['showArrow'] )->toBeFalse();
} );

test( 'read more block has keywords', function (): void {
	$block = new ReadMoreBlock();

	expect( $block->getKeywords() )->toContain( 'read' )
		->and( $block->getKeywords() )->toContain( 'more' )
		->and( $block->getKeywords() )->toContain( 'link' );
} );

test( 'read more block supports typography', function (): void {
	$block    = new ReadMoreBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'read more block supports color', function (): void {
	$block    = new ReadMoreBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'read more block supports spacing', function (): void {
	$block    = new ReadMoreBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'read more block is marked as dynamic', function (): void {
	$block = new ReadMoreBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );

test( 'read more block does not have toolbar controls by default', function (): void {
	$block    = new ReadMoreBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toBeArray();
} );
