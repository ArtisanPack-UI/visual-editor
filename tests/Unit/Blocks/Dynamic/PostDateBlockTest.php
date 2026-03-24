<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostDate\PostDateBlock;

test( 'post date block has correct type', function (): void {
	$block = new PostDateBlock();

	expect( $block->getType() )->toBe( 'post-date' );
} );

test( 'post date block has correct category', function (): void {
	$block = new PostDateBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post date block has content schema with format display type and link fields', function (): void {
	$block  = new PostDateBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'format', 'displayType', 'isLink' ] )
		->and( $schema['format']['type'] )->toBe( 'text' )
		->and( $schema['format']['default'] )->toBe( '' )
		->and( $schema['displayType']['type'] )->toBe( 'select' )
		->and( $schema['displayType']['default'] )->toBe( 'date' )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse();
} );

test( 'post date block display type options include date modified and both', function (): void {
	$block   = new PostDateBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['displayType']['options'] );

	expect( $options )->toContain( 'date', 'modified', 'both' );
} );

test( 'post date block default content has correct values', function (): void {
	$block    = new PostDateBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['format'] )->toBe( '' )
		->and( $defaults['displayType'] )->toBe( 'date' )
		->and( $defaults['isLink'] )->toBeFalse();
} );

test( 'post date block has keywords', function (): void {
	$block = new PostDateBlock();

	expect( $block->getKeywords() )->toContain( 'date' )
		->and( $block->getKeywords() )->toContain( 'time' );
} );

test( 'post date block supports typography', function (): void {
	$block    = new PostDateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post date block supports color', function (): void {
	$block    = new PostDateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post date block supports spacing', function (): void {
	$block    = new PostDateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post date block is marked as dynamic', function (): void {
	$block = new PostDateBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
