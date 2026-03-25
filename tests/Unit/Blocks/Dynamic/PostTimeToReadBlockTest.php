<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTimeToRead\PostTimeToReadBlock;

test( 'post time to read block has correct type', function (): void {
	$block = new PostTimeToReadBlock();

	expect( $block->getType() )->toBe( 'post-time-to-read' );
} );

test( 'post time to read block has correct category', function (): void {
	$block = new PostTimeToReadBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post time to read block has content schema with all fields', function (): void {
	$block  = new PostTimeToReadBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'wordsPerMinute', 'prefix', 'suffix' ] )
		->and( $schema['wordsPerMinute']['type'] )->toBe( 'text' )
		->and( $schema['wordsPerMinute']['default'] )->toBe( '200' )
		->and( $schema['prefix']['type'] )->toBe( 'text' )
		->and( $schema['prefix']['default'] )->toBe( '' )
		->and( $schema['suffix']['type'] )->toBe( 'text' )
		->and( $schema['suffix']['default'] )->toBe( ' min read' );
} );

test( 'post time to read block default content has correct values', function (): void {
	$block    = new PostTimeToReadBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['wordsPerMinute'] )->toBe( '200' )
		->and( $defaults['prefix'] )->toBe( '' )
		->and( $defaults['suffix'] )->toBe( ' min read' );
} );

test( 'post time to read block has keywords', function (): void {
	$block = new PostTimeToReadBlock();

	expect( $block->getKeywords() )->toContain( 'time' )
		->and( $block->getKeywords() )->toContain( 'read' );
} );

test( 'post time to read block supports typography', function (): void {
	$block    = new PostTimeToReadBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post time to read block supports color', function (): void {
	$block    = new PostTimeToReadBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post time to read block supports spacing', function (): void {
	$block    = new PostTimeToReadBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post time to read block is marked as dynamic', function (): void {
	$block = new PostTimeToReadBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
