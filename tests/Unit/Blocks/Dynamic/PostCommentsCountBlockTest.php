<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostCommentsCount\PostCommentsCountBlock;

test( 'post comments count block has correct type', function (): void {
	$block = new PostCommentsCountBlock();

	expect( $block->getType() )->toBe( 'post-comments-count' );
} );

test( 'post comments count block has correct category', function (): void {
	$block = new PostCommentsCountBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post comments count block has content schema with all fields', function (): void {
	$block  = new PostCommentsCountBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'format', 'singular', 'plural', 'showIcon', 'linkToComments' ] )
		->and( $schema['format']['type'] )->toBe( 'select' )
		->and( $schema['format']['default'] )->toBe( 'short' )
		->and( $schema['singular']['type'] )->toBe( 'text' )
		->and( $schema['singular']['default'] )->toBe( '' )
		->and( $schema['plural']['type'] )->toBe( 'text' )
		->and( $schema['plural']['default'] )->toBe( '' )
		->and( $schema['showIcon']['type'] )->toBe( 'toggle' )
		->and( $schema['showIcon']['default'] )->toBeFalse()
		->and( $schema['linkToComments']['type'] )->toBe( 'toggle' )
		->and( $schema['linkToComments']['default'] )->toBeFalse();
} );

test( 'post comments count block format options include number short and long', function (): void {
	$block   = new PostCommentsCountBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['format']['options'] );

	expect( $options )->toContain( 'number', 'short', 'long' );
} );

test( 'post comments count block default content has correct values', function (): void {
	$block    = new PostCommentsCountBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['format'] )->toBe( 'short' )
		->and( $defaults['singular'] )->toBe( '' )
		->and( $defaults['plural'] )->toBe( '' )
		->and( $defaults['showIcon'] )->toBeFalse()
		->and( $defaults['linkToComments'] )->toBeFalse();
} );

test( 'post comments count block has keywords', function (): void {
	$block = new PostCommentsCountBlock();

	expect( $block->getKeywords() )->toContain( 'comments' )
		->and( $block->getKeywords() )->toContain( 'count' );
} );

test( 'post comments count block supports typography', function (): void {
	$block    = new PostCommentsCountBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post comments count block supports color', function (): void {
	$block    = new PostCommentsCountBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post comments count block supports spacing', function (): void {
	$block    = new PostCommentsCountBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post comments count block is marked as dynamic', function (): void {
	$block = new PostCommentsCountBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
