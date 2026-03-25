<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentAuthorName\CommentAuthorNameBlock;

test( 'comment author name block has correct type', function (): void {
	$block = new CommentAuthorNameBlock();

	expect( $block->getType() )->toBe( 'comment-author-name' );
} );

test( 'comment author name block has correct category', function (): void {
	$block = new CommentAuthorNameBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment author name block has content schema with all fields', function (): void {
	$block  = new CommentAuthorNameBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'isLink' ] )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse();
} );

test( 'comment author name block default content has correct values', function (): void {
	$block    = new CommentAuthorNameBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['isLink'] )->toBeFalse();
} );

test( 'comment author name block has keywords', function (): void {
	$block = new CommentAuthorNameBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'author' )
		->and( $block->getKeywords() )->toContain( 'name' );
} );

test( 'comment author name block has parent constraint', function (): void {
	$block = new CommentAuthorNameBlock();

	expect( $block->getAllowedParents() )->toContain( 'comment-template' );
} );

test( 'comment author name block supports typography', function (): void {
	$block    = new CommentAuthorNameBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comment author name block supports color', function (): void {
	$block    = new CommentAuthorNameBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comment author name block is marked as dynamic', function (): void {
	$block = new CommentAuthorNameBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
