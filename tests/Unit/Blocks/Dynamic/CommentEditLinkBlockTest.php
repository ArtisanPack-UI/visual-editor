<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentEditLink\CommentEditLinkBlock;

test( 'comment edit link block has correct type', function (): void {
	$block = new CommentEditLinkBlock();

	expect( $block->getType() )->toBe( 'comment-edit-link' );
} );

test( 'comment edit link block has correct category', function (): void {
	$block = new CommentEditLinkBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment edit link block has content schema with all fields', function (): void {
	$block  = new CommentEditLinkBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'text' ] )
		->and( $schema['text']['type'] )->toBe( 'text' )
		->and( $schema['text']['default'] )->toBe( '' );
} );

test( 'comment edit link block default content has correct values', function (): void {
	$block    = new CommentEditLinkBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['text'] )->toBe( '' );
} );

test( 'comment edit link block has keywords', function (): void {
	$block = new CommentEditLinkBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'edit' );
} );

test( 'comment edit link block has parent constraint', function (): void {
	$block = new CommentEditLinkBlock();

	expect( $block->getAllowedParents() )->toContain( 'comment-template' );
} );

test( 'comment edit link block supports typography', function (): void {
	$block    = new CommentEditLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comment edit link block supports color', function (): void {
	$block    = new CommentEditLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comment edit link block is marked as dynamic', function (): void {
	$block = new CommentEditLinkBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
