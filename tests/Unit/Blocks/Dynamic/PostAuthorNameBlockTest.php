<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostAuthorName\PostAuthorNameBlock;

test( 'post author name block has correct type', function (): void {
	$block = new PostAuthorNameBlock();

	expect( $block->getType() )->toBe( 'post-author-name' );
} );

test( 'post author name block has correct category', function (): void {
	$block = new PostAuthorNameBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post author name block has content schema with link and byline fields', function (): void {
	$block  = new PostAuthorNameBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'isLink', 'byline' ] )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse()
		->and( $schema['byline']['type'] )->toBe( 'text' )
		->and( $schema['byline']['default'] )->toBe( '' );
} );

test( 'post author name block default content has correct values', function (): void {
	$block    = new PostAuthorNameBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['isLink'] )->toBeFalse()
		->and( $defaults['byline'] )->toBe( '' );
} );

test( 'post author name block has keywords', function (): void {
	$block = new PostAuthorNameBlock();

	expect( $block->getKeywords() )->toContain( 'author' )
		->and( $block->getKeywords() )->toContain( 'name' );
} );

test( 'post author name block supports typography', function (): void {
	$block    = new PostAuthorNameBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post author name block supports color', function (): void {
	$block    = new PostAuthorNameBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post author name block supports spacing', function (): void {
	$block    = new PostAuthorNameBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post author name block is marked as dynamic', function (): void {
	$block = new PostAuthorNameBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
