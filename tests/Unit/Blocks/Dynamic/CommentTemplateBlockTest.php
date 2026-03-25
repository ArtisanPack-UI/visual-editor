<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentTemplate\CommentTemplateBlock;

test( 'comment template block has correct type', function (): void {
	$block = new CommentTemplateBlock();

	expect( $block->getType() )->toBe( 'comment-template' );
} );

test( 'comment template block has correct category', function (): void {
	$block = new CommentTemplateBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment template block has empty content schema', function (): void {
	$block  = new CommentTemplateBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'comment template block has keywords', function (): void {
	$block = new CommentTemplateBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'template' );
} );

test( 'comment template block supports spacing', function (): void {
	$block    = new CommentTemplateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'comment template block supports border', function (): void {
	$block    = new CommentTemplateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'border' )
		->and( $supports['border']['color'] )->toBeTrue()
		->and( $supports['border']['width'] )->toBeTrue()
		->and( $supports['border']['radius'] )->toBeTrue();
} );

test( 'comment template block has allowed children', function (): void {
	$block = new CommentTemplateBlock();
	$meta  = $block->toArray();

	expect( $meta['allowedChildren'] )->toContain( 'comment-author-avatar' )
		->and( $meta['allowedChildren'] )->toContain( 'comment-author-name' )
		->and( $meta['allowedChildren'] )->toContain( 'comment-content' )
		->and( $meta['allowedChildren'] )->toContain( 'comment-date' )
		->and( $meta['allowedChildren'] )->toContain( 'comment-reply-link' )
		->and( $meta['allowedChildren'] )->toContain( 'comment-edit-link' );
} );

test( 'comment template block is marked as dynamic', function (): void {
	$block = new CommentTemplateBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
