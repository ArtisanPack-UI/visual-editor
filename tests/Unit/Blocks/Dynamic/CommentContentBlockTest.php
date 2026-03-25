<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentContent\CommentContentBlock;

test( 'comment content block has correct type', function (): void {
	$block = new CommentContentBlock();

	expect( $block->getType() )->toBe( 'comment-content' );
} );

test( 'comment content block has correct category', function (): void {
	$block = new CommentContentBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment content block has empty content schema', function (): void {
	$block  = new CommentContentBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'comment content block has keywords', function (): void {
	$block = new CommentContentBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'content' );
} );

test( 'comment content block has parent constraint', function (): void {
	$block = new CommentContentBlock();

	expect( $block->getAllowedParents() )->toContain( 'comment-template' );
} );

test( 'comment content block supports typography', function (): void {
	$block    = new CommentContentBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comment content block supports color', function (): void {
	$block    = new CommentContentBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comment content block supports spacing', function (): void {
	$block    = new CommentContentBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'comment content block is marked as dynamic', function (): void {
	$block = new CommentContentBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
