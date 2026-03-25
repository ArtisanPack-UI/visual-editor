<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentAuthorAvatar\CommentAuthorAvatarBlock;

test( 'comment author avatar block has correct type', function (): void {
	$block = new CommentAuthorAvatarBlock();

	expect( $block->getType() )->toBe( 'comment-author-avatar' );
} );

test( 'comment author avatar block has correct category', function (): void {
	$block = new CommentAuthorAvatarBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment author avatar block has content schema with all fields', function (): void {
	$block  = new CommentAuthorAvatarBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'size', 'borderRadius' ] )
		->and( $schema['size']['type'] )->toBe( 'select' )
		->and( $schema['size']['default'] )->toBe( 'md' )
		->and( $schema['borderRadius']['type'] )->toBe( 'text' )
		->and( $schema['borderRadius']['default'] )->toBe( '50%' );
} );

test( 'comment author avatar block size options include sm md lg', function (): void {
	$block   = new CommentAuthorAvatarBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['size']['options'] );

	expect( $options )->toContain( 'sm', 'md', 'lg' );
} );

test( 'comment author avatar block default content has correct values', function (): void {
	$block    = new CommentAuthorAvatarBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['size'] )->toBe( 'md' )
		->and( $defaults['borderRadius'] )->toBe( '50%' );
} );

test( 'comment author avatar block has keywords', function (): void {
	$block = new CommentAuthorAvatarBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'avatar' );
} );

test( 'comment author avatar block has parent constraint', function (): void {
	$block = new CommentAuthorAvatarBlock();

	expect( $block->getAllowedParents() )->toContain( 'comment-template' );
} );

test( 'comment author avatar block supports spacing', function (): void {
	$block    = new CommentAuthorAvatarBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'comment author avatar block is marked as dynamic', function (): void {
	$block = new CommentAuthorAvatarBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
