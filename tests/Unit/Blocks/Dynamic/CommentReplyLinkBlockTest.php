<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentReplyLink\CommentReplyLinkBlock;

test( 'comment reply link block has correct type', function (): void {
	$block = new CommentReplyLinkBlock();

	expect( $block->getType() )->toBe( 'comment-reply-link' );
} );

test( 'comment reply link block has correct category', function (): void {
	$block = new CommentReplyLinkBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment reply link block has content schema with all fields', function (): void {
	$block  = new CommentReplyLinkBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'text' ] )
		->and( $schema['text']['type'] )->toBe( 'text' )
		->and( $schema['text']['default'] )->toBe( '' );
} );

test( 'comment reply link block default content has correct values', function (): void {
	$block    = new CommentReplyLinkBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['text'] )->toBe( '' );
} );

test( 'comment reply link block has keywords', function (): void {
	$block = new CommentReplyLinkBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'reply' );
} );

test( 'comment reply link block has parent constraint', function (): void {
	$block = new CommentReplyLinkBlock();

	expect( $block->getAllowedParents() )->toContain( 'comment-template' );
} );

test( 'comment reply link block supports typography', function (): void {
	$block    = new CommentReplyLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comment reply link block supports color', function (): void {
	$block    = new CommentReplyLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comment reply link block is marked as dynamic', function (): void {
	$block = new CommentReplyLinkBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
