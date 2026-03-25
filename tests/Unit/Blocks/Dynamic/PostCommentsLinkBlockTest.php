<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostCommentsLink\PostCommentsLinkBlock;

test( 'post comments link block has correct type', function (): void {
	$block = new PostCommentsLinkBlock();

	expect( $block->getType() )->toBe( 'post-comments-link' );
} );

test( 'post comments link block has correct category', function (): void {
	$block = new PostCommentsLinkBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post comments link block has content schema with text and zero text fields', function (): void {
	$block  = new PostCommentsLinkBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'text', 'zeroText' ] )
		->and( $schema['text']['type'] )->toBe( 'text' )
		->and( $schema['text']['default'] )->toBe( '' )
		->and( $schema['zeroText']['type'] )->toBe( 'text' )
		->and( $schema['zeroText']['default'] )->toBe( '' );
} );

test( 'post comments link block default content has correct values', function (): void {
	$block    = new PostCommentsLinkBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['text'] )->toBe( '' )
		->and( $defaults['zeroText'] )->toBe( '' );
} );

test( 'post comments link block has keywords', function (): void {
	$block = new PostCommentsLinkBlock();

	expect( $block->getKeywords() )->toContain( 'comments' )
		->and( $block->getKeywords() )->toContain( 'link' );
} );

test( 'post comments link block supports typography', function (): void {
	$block    = new PostCommentsLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post comments link block supports color', function (): void {
	$block    = new PostCommentsLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post comments link block supports spacing', function (): void {
	$block    = new PostCommentsLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post comments link block is marked as dynamic', function (): void {
	$block = new PostCommentsLinkBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
