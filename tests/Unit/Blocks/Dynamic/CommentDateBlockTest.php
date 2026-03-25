<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentDate\CommentDateBlock;

test( 'comment date block has correct type', function (): void {
	$block = new CommentDateBlock();

	expect( $block->getType() )->toBe( 'comment-date' );
} );

test( 'comment date block has correct category', function (): void {
	$block = new CommentDateBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comment date block has content schema with all fields', function (): void {
	$block  = new CommentDateBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'format', 'isLink' ] )
		->and( $schema['format']['type'] )->toBe( 'select' )
		->and( $schema['format']['default'] )->toBe( 'relative' )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse();
} );

test( 'comment date block format options include relative and absolute', function (): void {
	$block   = new CommentDateBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['format']['options'] );

	expect( $options )->toContain( 'relative', 'absolute' );
} );

test( 'comment date block default content has correct values', function (): void {
	$block    = new CommentDateBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['format'] )->toBe( 'relative' )
		->and( $defaults['isLink'] )->toBeFalse();
} );

test( 'comment date block has keywords', function (): void {
	$block = new CommentDateBlock();

	expect( $block->getKeywords() )->toContain( 'comment' )
		->and( $block->getKeywords() )->toContain( 'date' );
} );

test( 'comment date block has parent constraint', function (): void {
	$block = new CommentDateBlock();

	expect( $block->getAllowedParents() )->toContain( 'comment-template' );
} );

test( 'comment date block supports typography', function (): void {
	$block    = new CommentDateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comment date block supports color', function (): void {
	$block    = new CommentDateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comment date block is marked as dynamic', function (): void {
	$block = new CommentDateBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
